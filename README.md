# 🌌 Jornada do Herói Interior — Oráculo RAG & IA

> *"O privilégio de uma vida é se tornar quem você realmente é."* — Carl G. Jung

Este é um aplicativo de autoconhecimento místico e gamificado que atua como um **Oráculo da Jornada do Herói**. O sistema guia o buscador pelas etapas psicológicas da travessia espiritual (Partida, Iniciação e Retorno), oferecendo insights, meditações e desafios comportamentais gerados por Inteligência Artificial e fundamentados na sabedoria de obras literárias ancestrais indexadas dinamicamente via **RAG (Retrieval-Augmented Generation)**.

---

## 🔮 Funcionalidades Sagradas

*   **🧙‍♂️ O Oráculo do Mentor (Gemma 4 Thinking):** Respostas reflexivas profundas e acolhedoras geradas pelo modelo de raciocínio analítico `gemma-4-26b-a4b-it` da Google.
*   **📚 Sabedoria Ancestral (RAG):** Busca semântica e similaridade de cosseno em banco vetorial com trechos extraídos de grandes clássicos:
    *   *O Homem e seus Símbolos* — Carl G. Jung (Psicologia Analítica & Sombra)
    *   *O Herói de Mil Faces* — Joseph Campbell (Mitologia & Jornada)
    *   *Meditações* — Marco Aurélio (Estoicismo & Dicotomia do Controle)
    *   *Mulheres que Correm com os Lobos* — Clarissa Pinkola Estés (Arquétipo Selvagem)
    *   *Sāti: Como o Buda entendia a Atenção Plena* (Presença & Mindfulness)
*   **⚙️ Indexação Incremental de Livros:** Comando do Laravel inteligente que analisa os PDFs, fatia o texto em blocos de 4500 caracteres (com 400 de sobreposição) e gera embeddings apenas dos blocos novos, protegendo a cota diária gratuita do Gemini.
*   **⚡ Renderização Instantânea:** Frontend desenvolvido em Angular com detecção de mudanças forçada via `ChangeDetectorRef`, garantindo carregamento e destravamento visual em tempo real no envio das reflexões.

---

## 🏗️ Arquitetura do Templo (Tecnologias)

*   **Frontend:** Angular (Standalone Components, RxJS, CSS Vanilla com temática *glassmorphic dark*)
*   **Backend:** Laravel 11 (PHP 8.3+)
*   **Banco de Dados:** SQLite (leve e sem dependências externas)
*   **Modelos de IA (Google AI Studio):**
    *   `gemini-embedding-2` (Endpoint `/v1/`): Geração de vetores matemáticos para o RAG.
    *   `gemma-4-26b-a4b-it` (Endpoint `/v1beta/`): Geração de texto com timeout ajustado para 120s para acomodar o fluxo de pensamento profundo (*Thinking*).

---

## 🚀 Como Iniciar a Jornada

### 🔮 1. Preparando o Altar (Backend Laravel)

Entre na pasta do backend:
```bash
cd backend
```

Instale as dependências do Composer:
```bash
composer install
```

Configure as variables de ambiente:
```bash
cp .env.example .env
php artisan key:generate
```

Abra o arquivo `.env` e configure sua chave de API do Gemini:
```env
GEMINI_API_KEY=sua_chave_do_google_ai_studio
GEMINI_GENERATION_MODEL=gemma-4-26b-a4b-it
```

Execute as migrações do banco de dados SQLite:
```bash
touch database/database.sqlite
php artisan migrate
```

#### 📖 Alimentando o Oráculo (Indexação dos Livros)
Para processar os PDFs da pasta `ideia/RAG` e salvar os embeddings no banco:
```bash
php artisan app:index-books
```
*   *Nota:* O comando é **incremental** e pulará os livros/blocos que já foram processados. Se desejar apagar o banco e indexar tudo do zero, utilize a flag `--clear`:
    ```bash
    php artisan app:index-books --clear
    ```

Inicie o servidor do Laravel:
```bash
php artisan serve
```
O backend estará de pé em `http://localhost:8000`.

---

### 💻 2. Acendendo a Lanterna (Frontend Angular)

Na raiz do projeto (onde está o `package.json` principal):
```bash
npm install
```

Inicie o servidor de desenvolvimento do Angular:
```bash
ng serve
```

Navegue no seu browser em: `http://localhost:4200`

---

## 🛡️ Segurança de Cota (Estratégias de Proteção)

*   **Rate Limits (RPM):** O script de embeddings agrupa blocos em lotes de 30 itens e insere uma pausa de 20s (`sleep(20)`) a cada lote, mantendo a taxa máxima em 90 requisições/minuto (dentro do limite gratuito de 100 RPM).
*   **Cota Diária (RPD):** O `RagService` possui lógica de retentativa exponencial em caso de erro `HTTP 429`. Se a cota diária de 1.000 requisições esgotar, o script incremental salva o progresso e permite continuar exatamente de onde parou no dia seguinte.
