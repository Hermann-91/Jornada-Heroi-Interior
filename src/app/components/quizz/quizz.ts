import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { JourneyService, JourneyResponse, User } from '../../services/journey';

@Component({
  selector: 'app-quizz',
  standalone: true,
  imports: [CommonModule, FormsModule], // Importa o FormsModule para podermos usar formulários (textarea)
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
  }

  public sairDoJogo(): void {
    this.currentUser = null;
    this.isStarted = false;
    this.carregarHerois();
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
        // Recebe o insight do RAG
        const insightData = res.journey_record?.mentor_insight;
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
        this.isLoading = false;

        // Armazena temporariamente os dados do dia seguinte para quando o usuário clicar em "Avançar"
        if (!this.isFinished) {
          this.nextQuestionData = res;
        }
        this.carregarHerois();
        this.cdr.detectChanges(); // Esconde o loader e exibe o insight imediatamente na tela!
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
}
