import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Quizz } from './quizz';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { User } from '../../services/journey';
import { describe, it, expect, beforeEach, afterEach } from 'vitest';

describe('Quizz', () => {
  let component: Quizz;
  let fixture: ComponentFixture<Quizz>;
  let httpMock: HttpTestingController; // Classe que intercepta e mocka chamadas de rede no Angular

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Quizz],
      providers: [
        provideHttpClient(),        // Provedor HTTP nativo do Angular
        provideHttpClientTesting()  // Provedor HTTP específico para simular requisições em testes
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(Quizz);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController); // Injeta o controlador de simulação de rede
    fixture.detectChanges(); // triggers ngOnInit -> GET /users

    // Resolve a listagem inicial do painel de heróis de forma síncrona
    const reqUsers = httpMock.expectOne('http://localhost:8000/api/users');
    reqUsers.flush([]);
  });

  afterEach(() => {
    httpMock.verify(); // Boa prática: Garante que nenhuma chamada HTTP simulada ficou pendente
  });

  /**
   * Teste 1: Garante que o componente é instanciado sem erros.
   */
  it('should create', () => {
    expect(component).toBeTruthy();
  });

  /**
   * Teste 2: Garante que a tela de seleção de herói (com o input e lista) aparece de início.
   */
  it('should render the user selection screen initially', () => {
    const compiled = fixture.nativeElement as HTMLElement; // Acessa o HTML renderizado (DOM)
    expect(compiled.querySelector('.title_spark')?.textContent).toContain('Oráculo do Herói');
    expect(compiled.querySelector('.intro_card h3')?.textContent).toContain('Selecione seu Herói');
  });

  /**
   * Teste 3: Simula o carregamento e seleção de um herói existente e valida a renderização.
   */
  it('should load user, select it, and transition to the question screen', async () => {
    const compiled = fixture.nativeElement as HTMLElement;

    const mockUser: User = {
      id: 1,
      name: 'Herói Teste',
      current_day: 1,
      phase: 'Partida / Separação',
      question: 'O que na sua vida atual parece estagnado?',
      finished: false
    };

    component.selecionarHeroi(mockUser);
    fixture.detectChanges();

    expect(component.isStarted).toBe(true);
    expect(component.currentDay).toBe(1);
    expect(component.phase).toBe('Partida / Separação');
    expect(component.question).toBe('O que na sua vida atual parece estagnado?');

    expect(compiled.querySelector('.badge_user')?.textContent).toContain('Herói: Herói Teste');
    expect(compiled.querySelector('.badge_day')?.textContent).toContain('Dia 1 da Jornada');
    expect(compiled.querySelector('.badge_phase')?.textContent).toContain('Partida / Separação');
  });

  /**
   * Teste 4: Valida o envio da reflexão, exibição dos 3 tópicos estruturados e avanço do dia.
   */
  it('should call respondQuestion API, show the 3 topics, and click advance', async () => {
    // 1. Inicia a jornada simulando a seleção de um usuário
    const compiled = fixture.nativeElement as HTMLElement;

    const mockUser: User = {
      id: 1,
      name: 'Herói Teste',
      current_day: 1,
      phase: 'Partida / Separação',
      question: 'O que na sua vida atual parece estagnado?',
      finished: false
    };

    component.selecionarHeroi(mockUser);
    fixture.detectChanges(); // Renderiza a tela de perguntas com o estado isStarted = true

    // 2. Define o valor no textarea e simula digitação (atualiza ngModel)
    const textarea = compiled.querySelector('textarea') as HTMLTextAreaElement;
    textarea.value = 'Estou me sentindo muito estagnado na carreira.';
    textarea.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    // 3. Simula o clique no botão "Consultar Oráculo"
    const submitButton = compiled.querySelector('.btn-primary') as HTMLButtonElement;
    submitButton.click();
    fixture.detectChanges();

    // 4. Intercepta e responde a requisição HTTP mockada
    const req = httpMock.expectOne('http://localhost:8000/api/journey/respond');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ user_id: 1, answer: 'Estou me sentindo muito estagnado na carreira.' });

    req.flush({
      message: 'Insight do Mentor gerado com sucesso!',
      journey_record: {
        user_id: 1,
        current_day: 1,
        phase: 'Partida / Separação',
        question: 'O que na sua vida atual parece estagnado?',
        user_answer: 'Estou me sentindo muito estagnado na carreira.',
        mentor_insight: {
          insight: 'O mentor vê que a estagnação é o início do chamado.',
          meditation: 'Respire fundo e aceite o chamado.',
          challenge: 'Faça um diário de bordo hoje.'
        }
      },
      next_day: 2,
      next_question: 'Qual é o seu próximo passo?',
      next_phase: 'Iniciação / Provações',
      finished: false
    });

    // 5. Aguarda as microtasks e atualiza a tela
    await fixture.whenStable();
    fixture.detectChanges();

    // Responde ao reload do carregarHerois() síncrono
    const reqUsersReload = httpMock.expectOne('http://localhost:8000/api/users');
    reqUsersReload.flush([]);

    expect(component.isDayCompleted).toBe(true);
    expect(component.mentorInsightText).toBe('O mentor vê que a estagnação é o início do chamado.');
    expect(component.mentorMeditation).toBe('Respire fundo e aceite o chamado.');
    expect(component.mentorChallenge).toBe('Faça um diário de bordo hoje.');

    expect(compiled.querySelector('.reflection_text')?.textContent).toContain('O mentor vê que a estagnação é o início do chamado.');
    expect(compiled.querySelector('.meditation_text')?.textContent).toContain('Respire fundo e aceite o chamado.');
    expect(compiled.querySelector('.challenge_text')?.textContent).toContain('Faça um diário de bordo hoje.');

    // 6. Simula o clique em avançar
    const nextButton = compiled.querySelector('.btn-next') as HTMLButtonElement;
    nextButton.click();

    await fixture.whenStable();
    fixture.detectChanges();
    expect(component.isDayCompleted).toBe(false);
    expect(component.currentDay).toBe(2);
    expect(component.phase).toBe('Iniciação / Provações');
    expect(component.question).toBe('Qual é o seu próximo passo?');
  });
});
