<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatGPTService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, string $openaiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $openaiApiKey;
    }

    /**
     * Demande à ChatGPT de jouer le tour d'une IA
     */
    public function playAITurn(array $character, int $diceResult, array $possibleMoves, array $gameState): array
    {
        try {
            $prompt = $this->buildTurnPrompt($character, $diceResult, $possibleMoves, $gameState);

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500
                ]
            ]);

            $data = $response->toArray();
            $aiResponse = $data['choices'][0]['message']['content'];

            // Parser la réponse JSON
            return json_decode($aiResponse, true);

        } catch (\Exception $e) {
            return [];
        }
    }

    private function getSystemPrompt(): string
    {
        return "Tu es un joueur IA dans une partie de Cluedo.

RÈGLES DU JEU:
- Le plateau fait 24x24 cases
- Tu peux te déplacer du nombre de cases indiqué par les dés (2-12)
- Les murs (1) bloquent le passage
- Les portes (2) permettent d'entrer dans les pièces
- Les pièces sont numérotées de 3 à 11
- Tu dois trouver le meurtrier, l'arme et le lieu du crime

PERSONNAGES:
- Colonel Moutarde (jaune)
- Mademoiselle Rose (rouge)
- Révérend Olive (vert)
- Professeur Violet (violet)
- Madame Leblanc (blanc)
- Docteur Lenoir (bleu)

LIEUX:
- Cuisine, Salle de billard, Bibliothèque, Véranda, Salle à manger, Salon, Hall, Bureau, Studio

ARMES:
- Poignard, Chandelier, Revolver, Corde, Clé anglaise, Matraque

PASSAGES SECRETS:
- Cuisine ↔ Bureau
- Salle de billard ↔ Véranda

Réponds UNIQUEMENT au format JSON suivant:
{
  \"action\": \"move\" | \"hypothesis\" | \"accusation\",
  \"target\": { \"x\": number, \"y\": number } (si action=move),
  \"hypothesis\": { \"location\": string, \"character\": string, \"weapon\": string } (si action=hypothesis),
  \"reasoning\": string (explication courte de ta décision)
}";
    }

    private function buildTurnPrompt(array $character, int $diceResult, array $possibleMoves, array $gameState): string
    {
        $otherPlayers = array_filter($gameState['characters'] ?? [], function($c) use ($character) {
            return $c['id'] !== $character['id'];
        });

        $otherPlayersStr = implode("\n", array_map(function($c) {
            return sprintf("- %s: (%d, %d)", $c['name'], $c['position']['x'], $c['position']['y']);
        }, $otherPlayers));

        $hypothesesStr = '';
        if (!empty($gameState['previousHypotheses'])) {
            $hypothesesStr = implode("\n", array_map(function($h) {
                return sprintf("- %s suggère: %s dans %s avec %s",
                    $h['character'],
                    $h['hypothesis']['character'] ?? '?',
                    $h['hypothesis']['location'] ?? '?',
                    $h['hypothesis']['weapon'] ?? '?'
                );
            }, array_slice($gameState['previousHypotheses'], -3)));
        }

        return sprintf("C'est ton tour de jouer !

PERSONNAGE: %s
POSITION ACTUELLE: (%d, %d)
RÉSULTAT DÉS: %d
NOMBRE DE MOUVEMENTS POSSIBLES: %d

POSITIONS DES AUTRES JOUEURS:
%s

HYPOTHÈSES PRÉCÉDENTES:
%s

CARTES QUE TU DÉTIENS:
%s

Que fais-tu ? Réponds au format JSON uniquement.",
            $character['name'],
            $character['position']['x'],
            $character['position']['y'],
            $diceResult,
            count($possibleMoves),
            $otherPlayersStr ?: 'Aucune',
            $hypothesesStr ?: 'Aucune',
            implode(', ', $character['cards'] ?? []) ?: 'Non révélées'
        );
    }
}
