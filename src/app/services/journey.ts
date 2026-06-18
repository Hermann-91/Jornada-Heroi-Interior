import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

// Tipagem profissional para garantir autocomplete no TypeScript (Interface)
export interface User {
  id: number;
  name: string;
  current_day: number;
  phase: string;
  question: string;
  finished: boolean;
  created_at?: string;
}

export interface JourneyResponse {
  message: string;
  current_day?: number;
  phase?: string;
  question?: string;
  journey_record?: any;
  next_day?: number | null;
  next_question?: string | null;
  next_phase?: string | null;
  finished?: boolean;
}

@Injectable({
  providedIn: 'root' // Torna o serviço disponível globalmente na aplicação
})
export class JourneyService {
  // Injeção de dependência moderna utilizando a função inject()
  private readonly http = inject(HttpClient);
  
  // URL local do Laravel
  private readonly apiUrl = 'http://localhost:8000/api';

  /**
   * Busca a lista de heróis do backend.
   */
  getUsers(): Observable<User[]> {
    return this.http.get<User[]>(`${this.apiUrl}/users`);
  }

  createUser(name: string): Observable<{ message: string; user: User }> {
    return this.http.post<{ message: string; user: User }>(`${this.apiUrl}/users`, { name });
  }

  deleteUser(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/users/${id}`);
  }

  /**
   * Dispara a rota que limpa o progresso e inicia o jogo.
   */
  startJourney(userId: number): Observable<JourneyResponse> {
    return this.http.post<JourneyResponse>(`${this.apiUrl}/journey/start`, { user_id: userId });
  }

  /**
   * Envia a resposta reflexiva do usuário para ser salva e processada pelo RAG.
   */
  respondQuestion(userId: number, answer: string): Observable<JourneyResponse> {
    return this.http.post<JourneyResponse>(`${this.apiUrl}/journey/respond`, { user_id: userId, answer });
  }
}
