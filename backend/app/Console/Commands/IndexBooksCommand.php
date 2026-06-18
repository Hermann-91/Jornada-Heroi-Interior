<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RagService;
use App\Models\BookChunk;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\File;

class IndexBooksCommand extends Command
{
    /**
     * A assinatura do comando (como você vai digitá-lo no terminal).
     * O formato 'app:index-books' vira uma instrução Artisan.
     */
    protected $signature = 'app:index-books {--clear : Limpa todos os blocos do banco SQLite antes de iniciar}';

    /**
     * A descrição do comando que aparece no menu do Laravel Artisan.
     */
    protected $description = 'Lê os livros em PDF da pasta ideia/RAG, fatia o texto em blocos e gera embeddings no banco via Gemini';

    private RagService $ragService;
    private Parser $pdfParser;

    /**
     * Construtor da classe de comando.
     * Usamos Injeção de Dependência para obter a nossa classe RagService criada anteriormente.
     */
    public function __construct(RagService $ragService)
    {
        parent::__construct();
        $this->ragService = $ragService;
        $this->pdfParser = new Parser(); // Inicializa a biblioteca de leitura de PDFs
    }

    /**
     * Executa a lógica principal do comando no console.
     */
    public function handle()
    {
        $this->info("=== INICIANDO INDEXAÇÃO DE LIVROS (RAG) ===");

        // Mapeia o caminho da pasta de livros.
        // base_path() traz o caminho absoluto da pasta backend.
        // Subimos um nível com '../' para acessar a pasta 'ideia/RAG'.
        $ragPath = base_path('../ideia/RAG');

        // Validação defensiva: se a pasta não existir, aborta o script
        if (!File::exists($ragPath)) {
            $this->error("Erro: A pasta RAG não foi encontrada no caminho: {$ragPath}");
            return Command::FAILURE;
        }

        // Obtém todos os arquivos da pasta e filtra apenas os PDFs
        $files = File::files($ragPath);
        $pdfFiles = array_filter($files, fn($file) => $file->getExtension() === 'pdf');

        if (empty($pdfFiles)) {
            $this->error("Aviso: Nenhum arquivo PDF foi encontrado em: {$ragPath}");
            return Command::FAILURE;
        }

        // Medida de segurança contra duplicação de dados
        if ($this->option('clear')) {
            BookChunk::truncate();
            $this->info("Banco de dados SQLite limpo com sucesso!");
        } else {
            $this->info("Modo Incremental ativo: os blocos já indexados no SQLite serão preservados.");
        }

        // Loop principal: lê e processa um livro por vez
        foreach ($pdfFiles as $file) {
            $fileName = $file->getFilename();
            $this->warn("\n------------------------------------------------");
            $this->info("Lendo arquivo: {$fileName}");

            try {
                // 1. Extração de texto do PDF usando a biblioteca smalot/pdfparser
                $pdf = $this->pdfParser->parseFile($file->getRealPath());
                $text = $pdf->getText();

                // 2. Limpeza básica de texto: remove quebras de linha e espaços duplos
                $text = preg_replace('/\s+/', ' ', $text);

                // 3. Fatiamento em chunks usando o nosso RagService
                // Aumentamos o chunk para 4500 caracteres para economizar requisições e caber na cota diária
                $chunks = $this->ragService->splitText($text, 4500, 400);
                // Filtra blocos muito pequenos antes do agrupamento
                $filteredChunks = array_values(array_filter($chunks, fn($chunk) => strlen(trim($chunk)) >= 30));
                $totalFiltered = count($filteredChunks);

                $this->info("Texto fatiado com sucesso em {$totalFiltered} blocos válidos.");

                // Identifica quais fatias ainda não possuem embedding no banco SQLite (Modo Incremental)
                $chunksToProcess = [];
                foreach ($filteredChunks as $index => $chunkContent) {
                    $exists = BookChunk::where('book_title', $fileName)
                        ->where('chunk_index', $index)
                        ->whereNotNull('embedding')
                        ->exists();

                    if (!$exists) {
                        $chunksToProcess[$index] = $chunkContent;
                    }
                }

                $totalToProcess = count($chunksToProcess);
                if ($totalToProcess === 0) {
                    $this->info("✓ Livro já está totalmente indexado e com embeddings gerados!");
                    continue;
                }

                $alreadyIndexed = $totalFiltered - $totalToProcess;
                $this->warn("Apenas {$totalToProcess} novos blocos serão processados (os outros {$alreadyIndexed} já existem no banco).");
                $this->info("Gerando embeddings vetoriais em lote com a API do Gemini...");

                $bar = $this->output->createProgressBar($totalToProcess);
                $bar->start();

                // Divide as fatias em lotes seguros de 30 itens, preservando o índice original como chave
                $batches = array_chunk($chunksToProcess, 30, true);

                foreach ($batches as $batchIndex => $batchChunks) {
                    // Gera embeddings em lote (passamos apenas os valores textuais para a API)
                    $embeddings = $this->ragService->generateBatchEmbeddings(array_values($batchChunks));

                    // Grava os novos blocos no SQLite
                    $i = 0;
                    foreach ($batchChunks as $originalIndex => $chunkContent) {
                        BookChunk::create([
                            'book_title' => $fileName,
                            'chunk_index' => $originalIndex,
                            'content' => $chunkContent,
                            'embedding' => $embeddings[$i] ?? []
                        ]);
                        $i++;
                        $bar->advance();
                    }

                    // Pausa de 20 segundos para manter o limite de RPM abaixo de 100 (3 lotes de 30 = 90 requisições/min)
                    if ($batchIndex < count($batches) - 1) {
                        sleep(20);
                    }
                }

                $bar->finish();
                $this->info("\nSucesso: {$fileName} indexado perfeitamente!");

            } catch (\Exception $e) {
                $this->error("\nErro ao indexar {$fileName}: " . $e->getMessage());
                $this->info("Certifique-se de que sua GEMINI_API_KEY está configurada corretamente no arquivo .env");
            }
        }

        $this->info("\n=== PROCESSO DE INDEXAÇÃO CONCLUÍDO ===");
        return Command::SUCCESS;
    }
}
