import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { JourneyService, JourneyResponse, User, JourneyStage } from '../../services/journey';
import { XpCardComponent } from '../xp-card/xp-card.component';
import { AttributesCardComponent } from '../attributes-card/attributes-card.component';
import { JourneyTrailCardComponent } from '../journey-trail-card/journey-trail-card.component';

@Component({
  selector: 'app-quizz',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    XpCardComponent,
    AttributesCardComponent,
    JourneyTrailCardComponent
  ], // Importa o FormsModule para podermos usar formulários (textarea) e os subcomponentes de gamificação
  templateUrl: './quizz.html',
  styleUrl: './quizz.css',
})
export class Quizz implements OnInit {
  // Injeção de dependência do serviço de jornada do Laravel
  private readonly journeyService = inject(JourneyService);
  private readonly cdr = inject(ChangeDetectorRef);

  // Estados de controle reativos (Signals e Variáveis comuns declarados como PUBLIC para acesso nos testes)
  public title = 'Oráculo do Herói';
  public currentDay = 0;
  public phase = '';
  public question = '';
  
  public answerInput = ''; // Guarda a reflexão que o usuário está digitando
  public mentorInsightText = '';
  public mentorMeditation = '';
  public mentorChallenge = '';
  
  public isStarted = false;
  public isLoading = false; // Exibe o status de "pensando..." da IA
  public isDayCompleted = false; // Controla se o dia atual já foi respondido
  public isFinished = false;

  // Histórico e Gamificação
  public history: JourneyStage[] = [];
  public selectedStage: JourneyStage | null = null;
  public showHistoryModal = false;

  // XP e Nível
  public xp = 0;
  public levelNum = 1;
  public levelName = 'Iniciado Solitário';
  public xpPercentage = 0;

  // Atributos de Sabedoria
  public attributeStoicism = 0;
  public attributeJung = 0;
  public attributeSati = 0;
  public attributeCampbell = 0;

  public readonly totalJourneyDays = Array.from({ length: 21 }, (_, i) => i + 1);

  // Múltiplos Usuários (Painel)
  public users: User[] = [];
  public currentUser: User | null = null;
  public newUserName = '';

  // Variável de apoio para guardar o estado do próximo dia
  private nextQuestionData?: JourneyResponse;

  ngOnInit(): void {
    this.carregarHerois();
  }

  public carregarHerois(): void {
    this.isLoading = true;
    this.cdr.detectChanges(); // Força exibição do loader
    this.journeyService.getUsers().subscribe({
      next: (res: User[]) => {
        this.users = res;
        this.isLoading = false;
        this.cdr.detectChanges(); // Atualiza a lista na tela
      },
      error: (err) => {
        console.error('Erro ao buscar heróis:', err);
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  public criarHeroi(): void {
    if (!this.newUserName.trim()) {
      alert('Por favor, digite um nome para o seu herói.');
      return;
    }

    this.isLoading = true;
    this.cdr.detectChanges();
    this.journeyService.createUser(this.newUserName).subscribe({
      next: (res) => {
        this.newUserName = '';
        this.carregarHerois();
        this.selecionarHeroi(res.user);
        this.cdr.detectChanges(); // Atualiza a tela com o novo herói selecionado
      },
      error: (err) => {
        console.error('Erro ao criar herói:', err);
        alert('Erro ao criar herói. Nome já existente ou inválido.');
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  public deletarHeroi(id: number, event: Event): void {
    event.stopPropagation(); // Evita selecionar o herói ao clicar em apagar
    if (!confirm('Tem certeza de que deseja excluir este herói? Todo o histórico da jornada dele será perdido.')) {
      return;
    }

    this.isLoading = true;
    this.cdr.detectChanges();
    this.journeyService.deleteUser(id).subscribe({
      next: () => {
        if (this.currentUser?.id === id) {
          this.currentUser = null;
          this.isStarted = false;
        }
        this.carregarHerois();
        this.cdr.detectChanges(); // Atualiza a tela após deletar
      },
      error: (err) => {
        console.error('Erro ao excluir herói:', err);
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  public selecionarHeroi(user: User): void {
    this.currentUser = user;
    this.currentDay = user.current_day;
    this.phase = user.phase || 'Partida / Separação';
    this.question = user.question || '';
    this.isStarted = true;
    this.isDayCompleted = false;
    this.isFinished = user.finished || false;
    this.mentorInsightText = '';
    this.mentorMeditation = '';
    this.mentorChallenge = '';
    this.answerInput = '';
    this.carregarHistorico(user.id); // Carrega o histórico e XP
  }

  public sairDoJogo(): void {
    this.currentUser = null;
    this.isStarted = false;
    this.history = [];
    this.xp = 0;
    this.carregarHerois();
    this.cdr.detectChanges();
  }

  /**
   * Inicia o jogo, resetando os dados e trazendo a primeira pergunta do Laravel.
   */
  public iniciarJornada(): void {
    if (!this.currentUser) return;
    this.isLoading = true;
    this.cdr.detectChanges();
    this.journeyService.startJourney(this.currentUser.id).subscribe({
      next: (res: JourneyResponse) => {
        this.currentDay = res.current_day || 1;
        this.phase = res.phase || 'Partida';
        this.question = res.question || '';
        this.isStarted = true;
        this.isDayCompleted = false;
        this.isFinished = false;
        this.mentorInsightText = '';
        this.mentorMeditation = '';
        this.mentorChallenge = '';
        this.answerInput = '';
        this.isLoading = false;
        
        // Reseta a gamificação localmente
        this.history = [];
        this.xp = 0;
        this.xpPercentage = 0;
        this.levelNum = 1;
        this.levelName = 'Iniciado Solitário';
        this.attributeStoicism = 0;
        this.attributeJung = 0;
        this.attributeSati = 0;
        this.attributeCampbell = 0;

        this.carregarHerois();
        this.cdr.detectChanges(); // Atualiza a tela com o início da jornada
      },
      error: (err) => {
        console.error('Erro ao iniciar jornada:', err);
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  /**
   * Envia a resposta digitada pelo usuário para a API.
   */
  public enviarReflexao(): void {
    if (!this.currentUser) return;
    if (this.answerInput.trim().length < 10) {
      alert('Por favor, escreva uma reflexão de pelo menos 10 caracteres para que o Mentor possa analisar.');
      return;
    }

    this.isLoading = true;
    this.cdr.detectChanges(); // Garante a exibição imediata do loader na tela

    this.journeyService.respondQuestion(this.currentUser.id, this.answerInput).subscribe({
      next: (res: JourneyResponse) => {
        const record = res.journey_record;
        const insightData = record?.mentor_insight;
        const isDayCompleted = !!res.is_day_completed;

        if (isDayCompleted) {
          // Revelação final do dia (Passo 3 respondido)
          if (insightData && typeof insightData === 'object') {
            this.mentorInsightText = insightData.insight || '';
            this.mentorMeditation = insightData.meditation || '';
            this.mentorChallenge = insightData.challenge || '';
          } else {
            this.mentorInsightText = typeof insightData === 'string' ? insightData : '';
            this.mentorMeditation = '';
            this.mentorChallenge = '';
          }
          this.isDayCompleted = true;
          this.isFinished = res.finished || false;

          // Armazena temporariamente os dados do dia seguinte para quando o herói avançar
          if (!this.isFinished) {
            this.nextQuestionData = res;
          }
        } else {
          // Diálogo intermediário (Passo 1 ou 2 respondido)
          // Exibe o insight curto da IA e carrega a subpergunta gerada para a caixa de texto
          this.mentorInsightText = insightData?.insight || '';
          this.question = res.next_question || '';
          this.isDayCompleted = false;
        }

        this.answerInput = ''; // Limpa o textarea para a próxima reflexão
        this.isLoading = false;
        this.carregarHerois();
        this.carregarHistorico(this.currentUser!.id); // Atualiza os atributos e o mapa de dias
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Erro ao enviar reflexão:', err);
        this.isLoading = false;
        this.cdr.detectChanges(); // Destrava a tela em caso de erro
      }
    });
  }

  /**
   * Avança a jornada do herói para o dia seguinte (nome corrigido para ASCII simples).
   */
  public avancarDia(): void {
    if (this.nextQuestionData) {
      this.currentDay = this.nextQuestionData.next_day || this.currentDay;
      this.phase = this.nextQuestionData.next_phase || this.phase;
      this.question = this.nextQuestionData.next_question || '';
      
      this.isDayCompleted = false;
      this.answerInput = '';
      this.mentorInsightText = '';
      this.mentorMeditation = '';
      this.mentorChallenge = '';
      this.nextQuestionData = undefined;
    }
  }

  // Métodos de Controle do Mapa e Gamificação
  public carregarHistorico(userId: number): void {
    this.journeyService.getJourneyHistory(userId).subscribe({
      next: (res: JourneyStage[]) => {
        this.history = res;
        this.calcularGamificacao();
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Erro ao carregar histórico:', err);
      }
    });
  }

  public calcularGamificacao(): void {
    let totalXp = 0;
    let stoicismPoints = 0;
    let jungPoints = 0;
    let satiPoints = 0;
    let campbellPoints = 0;

    this.history.forEach(stage => {
      // 100 XP por dia concluído
      totalXp += 100;
      
      // Bônus de profundidade (mais de 150 caracteres)
      const answerLength = stage.user_answer ? stage.user_answer.trim().length : 0;
      if (answerLength >= 150) {
        totalXp += 50;
        satiPoints += 25; // Reflexões profundas ativam a Atenção Plena
      } else {
        satiPoints += 10;
      }

      // Pontos de atributos baseados na fase psicológica
      const phaseLower = stage.phase.toLowerCase();
      if (phaseLower.includes('partida') || phaseLower.includes('separa')) {
        stoicismPoints += 33; // 3 dias na Partida = 100% de Estoicismo
      } else if (phaseLower.includes('inicia') || phaseLower.includes('prova')) {
        jungPoints += 33;     // 3 dias na Iniciação = 100% de Jung/Sombra
      } else if (phaseLower.includes('retorno') || phaseLower.includes('integra')) {
        campbellPoints += 50; // 2 dias no Retorno = 100% de Campbell/Herói
      }
    });

    this.xp = totalXp;

    // Nível de Consciência: Cada nível requer 400 XP
    const levelThreshold = 400;
    this.levelNum = Math.floor(this.xp / levelThreshold) + 1;
    const xpInCurrentLevel = this.xp % levelThreshold;
    this.xpPercentage = Math.min(Math.round((xpInCurrentLevel / levelThreshold) * 100), 100);

    const titles = [
      'Iniciado Solitário',
      'Buscador da Verdade',
      'Explorador da Sombra',
      'Guerreiro Consciente',
      'Sábio Integrado',
      'Mestre do Self'
    ];
    this.levelName = titles[Math.min(this.levelNum - 1, titles.length - 1)];

    // Limita os atributos de 0 a 100
    this.attributeStoicism = Math.min(stoicismPoints, 100);
    this.attributeJung = Math.min(jungPoints, 100);
    this.attributeSati = Math.min(satiPoints, 100);
    this.attributeCampbell = Math.min(campbellPoints, 100);
  }

  public abrirDetalheDia(stage: JourneyStage): void {
    this.selectedStage = stage;
    this.showHistoryModal = true;
    this.cdr.detectChanges();
  }

  public fecharModal(): void {
    this.selectedStage = null;
    this.showHistoryModal = false;
    this.cdr.detectChanges();
  }

  public obterEstagioDoDia(day: number): JourneyStage | undefined {
    return this.history.find(stage => stage.current_day === day);
  }
}
