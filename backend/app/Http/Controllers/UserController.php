<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserJourney;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Lista todos os heróis cadastrados e o dia atual da jornada deles.
     */
    public function index()
    {
        $users = User::orderBy('id', 'desc')->get();

        $formatted = $users->map(function ($user) {
            // Pega o último registro de jornada concluído por esse usuário
            $lastEntry = UserJourney::where('user_id', $user->id)->latest('id')->first();

            if (!$lastEntry) {
                // Estado inicial: jornada não iniciada
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'current_day' => 1,
                    'phase' => 'Partida / Separação',
                    'question' => 'O que na sua vida atual parece estagnado ou precisando de mudança?',
                    'finished' => false,
                    'created_at' => $user->created_at,
                ];
            }

            $lastInsight = $lastEntry->mentor_insight;
            $finished = (bool)($lastInsight['finished'] ?? false);

            if ($finished) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'current_day' => $lastEntry->current_day,
                    'phase' => $lastEntry->phase,
                    'question' => $lastEntry->question,
                    'finished' => true,
                    'created_at' => $user->created_at,
                ];
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'current_day' => $lastEntry->current_day + 1,
                'phase' => $lastInsight['next_phase'] ?? 'Iniciação / Provações',
                'question' => $lastInsight['next_question'] ?? 'Como você se sente com relação a esse momento?',
                'finished' => false,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json($formatted, 200);
    }

    /**
     * Cadastra um novo herói.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:users,name'
        ]);

        $user = User::create([
            'name' => $request->input('name')
        ]);

        return response()->json([
            'message' => 'Herói cadastrado com sucesso!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'current_day' => 0
            ]
        ], 201); // 201 Created
    }

    /**
     * Exclui um herói e seu histórico.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Herói não encontrado.'], 404);
        }

        $user->delete(); // Apaga o usuário (e o cascade delete limpa as jornadas dele)

        return response()->json(['message' => 'Herói excluído com sucesso!'], 200);
    }
}
