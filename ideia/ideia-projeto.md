# 🎮 Journey Hero - Projeto de Transformação Pessoal

## 📋 Quizz de Perguntas e Respostas

O sistema funcionará como um jogo de psicologia e autoconhecimento, com as seguintes funcionalidades:

- ✅ Fazer perguntas e respostas para o usuário
- ✅ A partir das respostas, verificar o caminho que deve prosseguir no jogo para se tornar o seu eu Heroi pessoal
- ✅ Implementar com IA e gerar perguntas personalizadas a partir das respostas do usuário
- ✅ Jogo de psicologia e autoconhecimento

---

## 🎯 Objetivo

**Jornada do Herói** - Descobrindo seu eu interior e despertando sua força maior.

A estrutura para o seu software de transformação pessoal une:
- 🎲 Gamificação
- 🧠 Psicologia Arquetípica
- 🤖 Inteligência Artificial Generativa

> Para que a vivência seja profunda e diária, o sistema deve funcionar como um **oráculo interativo** e um **diário de bordo espiritual**.

---

## 🗺️ Estrutura Arquitetônica do Software

O aplicativo deve ser dividido em **três grandes macrofases**, contendo microestações diárias (um total de 12 a 21 dias recomendados para criar hábito).

```
[Mundo Comum] ──────▶ [Mundo Extraordinário] ──────▶ [Retorno Transformado]
   (Despertar)              (Provas e Cura)               (Integração)
```

### 1️⃣ Partida / Separação (Dias 1 a 4)

| Fase | Descrição |
|------|-----------|

| **O Chamado à Aventura** | O quiz inicial mapeia a insatisfação atual do usuário (Ex: "O que na sua vida atual parece estagnado?") |

| **Recusa do Chamado** | Identificação de medos, desculpas e resistências internas |

| **Encontro com o Mentor** | O software (via IA) assume o papel de mentor, oferecendo os primeiros textos de apoio |

### 2️⃣ Iniciação / Provações (Dias 5 a 14)

| Fase | Descrição |
|------|-----------|

| **Travessia do Umbral** | Rituais de quebra de rotina (início das meditações diárias) |

| **Testes, Aliados e Inimigos** | Quizzes focados em identificar gatilhos emocionais e autossabotagem |

| **A Caverna Secreta (A Prova Suprema)** | Enfrentamento da Sombra (conceito de Jung). Dias de silêncio ou escrita terapêutica profunda |

### 3️⃣ Retorno / Integração (Dias 15 a 21)

| Fase | Descrição |
|------|-----------|

| **A Recompensa / Ressurreição** | O usuário consolida o aprendizado e "morre" para a versão antiga de si mesmo |

| **Retorno com o Elixir** | Definição de metas práticas para aplicar a nova consciência no dia a dia real |

---

## 🧠 Arquitetura do RAG (Base de Conhecimento da IA)

Para alimentar o motor de busca e contextualização (RAG) da IA, insira obras fundamentais que cruzem psicologia, mitologia e espiritualidade:

### 📚 Obras Recomendadas

| Autor | Obra | Foco |
|-------|------|------|
| **Joseph Campbell** | O Herói de Mil Faces / O Poder do Mito | Mitologia e estrutura da jornada |

| **Carl G. Jung** | O Homem e seus Símbolos / Arquetipos e o Inconsciente Coletivo | Psicologia analítica e arquétipos |

| **Clarissa Pinkola Estés** | Mulheres que Correm com os Lobos | Arquétipo do selvagem/intuição |

| **Robert A. Johnson** | He, She e Inner Work | Trabalho prático com sombras e sonhos |

| **Filosofias Práticas** | Meditações (Marco Aurélio) / Textos Budistas | Estoicismo e atenção plena |

---

## 🎮 Dinâmica do Quiz e Práticas Diárias

> O quiz **não deve pontuar "certo ou errado"**, mas sim rastrear o estado de consciência e direcionar a prática do dia.

### Exemplo de Fluxo Diário (Dia 7 - Enfrentando a Sombra)

#### 1️⃣ O Quiz de Diagnóstico (Manhã)

**Pergunta:** *"Quando alguém critica você injustamente nas redes sociais ou no trabalho, qual é seu primeiro impulso inconsciente?"*

- **A)** Sinto uma raiva avassaladora e vontade de revidar imediatamente.  
  *→ (Arquétipo do Guerreiro Desalinhado)*
- **B)** Fico profundamente triste, me isolo e me sinto uma vítima.  
  *→ (Arquétipo Mártir)*
- **C)** Finjo que não me importo, mas fico remoendo aquilo por dias.  
  *→ (Arquétipo do Sabotador Silencioso)*

#### 2️⃣ Resposta da IA com RAG

Se o usuário escolhe a **Opção A**, a IA busca na base (Campbell/Jung) e responde:

> *"Você escolheu o caminho do embate direto. Segundo Jung, aquilo que nos irrita nos outros pode ser a projeção de nossa própria sombra não integrada. Campbell nos lembra que os monstros que enfrentamos fora costumam ser os reflexos dos nossos medos internos."*

#### 3️⃣ A Prática Recomendada (Tarde/Noite)

- 🧘 **Meditação Guiada (10 min):** Visualização de um espelho. Encarar a pessoa que te irrita e extrair a qualidade que você rejeita nela.
- ✍️ **Ação Prática:** Escrever uma carta de desabafo (que nunca será enviada) e depois queimá-la como ato simbólico de desapego.

---

## 💻 Engenharia do Sistema: Como Conectar Tudo

### 🎨 Front-end
Interface limpa, com estética minimalista (estilo **Headspace** ou **Stoic**), usando cores que evoluem conforme o herói avança:
- 🌑 Tons escuros na caverna
- ☀️ Tons luminosos no retorno

### ⚙️ Back-end & Banco de Vetores
Utilize ferramentas como **Pinecone** ou **ChromaDB** para armazenar os trechos dos livros fragmentados em embeddings.

### 🤖 Orquestrador de IA (LangChain / LlamaIndex)
Quando o usuário responde ao quiz, o sistema envia:
- A resposta atual
- O histórico do usuário

Para o LLM (como o **GPT-4o**), cruzando com os dados do banco de vetores para gerar um **feedback 100% personalizado**.


