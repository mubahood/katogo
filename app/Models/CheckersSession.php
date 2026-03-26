<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CheckersSession extends Model
{
    protected $fillable = [
        'session_code', 'status',
        'player1_id', 'player1_name',
        'player2_id', 'player2_name',
        'board_state', 'current_turn', 'current_turn_user_id',
        'last_move_from', 'last_move_to', 'last_captured', 'last_crowned',
        'winner_id', 'winner_name', 'move_count',
        'player1_last_poll', 'player2_last_poll',
        'chat_head_id',
        'started_at', 'ended_at', 'expires_at',
    ];

    protected $casts = [
        'board_state' => 'array',
        'last_captured' => 'array',
        'last_crowned' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
        'player1_last_poll' => 'datetime',
        'player2_last_poll' => 'datetime',
    ];

    // ── Initial board setup ──────────────────────────────────────────────
    public static function initialBoard(): array
    {
        $board = array_fill(0, 32, null);
        // Black pieces: indices 0..11
        for ($i = 0; $i < 12; $i++) {
            $board[$i] = ['color' => 'black', 'isKing' => false];
        }
        // Red pieces: indices 20..31
        for ($i = 20; $i < 32; $i++) {
            $board[$i] = ['color' => 'red', 'isKing' => false];
        }
        return $board;
    }

    // ── Create new session from invitation acceptance ─────────────────────
    public static function createFromInvitation(
        int $player1Id, string $player1Name,
        int $player2Id, string $player2Name
    ): self {
        $session = self::create([
            'session_code' => strtoupper(Str::random(6)),
            'status' => 'active',
            'player1_id' => $player1Id,
            'player1_name' => $player1Name,
            'player2_id' => $player2Id,
            'player2_name' => $player2Name,
            'board_state' => self::initialBoard(),
            'current_turn' => 'red',
            'current_turn_user_id' => $player1Id, // Player 1 = red
            'started_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        return $session;
    }

    // ── Create from room code (private game) ─────────────────────────────
    public static function createRoom(int $hostId, string $hostName): self
    {
        return self::create([
            'session_code' => strtoupper(Str::random(6)),
            'status' => 'pending',
            'player1_id' => $hostId,
            'player1_name' => $hostName,
            'board_state' => self::initialBoard(),
            'current_turn' => 'red',
            'current_turn_user_id' => $hostId,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    // ── Coordinate helpers ───────────────────────────────────────────────
    public static function indexToRowCol(int $idx): array
    {
        $row = intdiv($idx, 4);
        $col = ($idx % 4) * 2 + ($row % 2 === 0 ? 1 : 0);
        return [$row, $col];
    }

    public static function rowColToIndex(int $row, int $col): int
    {
        if ($row < 0 || $row > 7 || $col < 0 || $col > 7) return -1;
        if (($row + $col) % 2 === 0) return -1; // light square
        return $row * 4 + intdiv($col, 2);
    }

    // ── Validate a move ──────────────────────────────────────────────────
    public function validateMove(int $from, int $to, int $userId): array
    {
        if ($this->status !== 'active') {
            return ['valid' => false, 'error' => 'Game is not active'];
        }
        if ($this->current_turn_user_id !== $userId) {
            return ['valid' => false, 'error' => 'Not your turn'];
        }

        $board = $this->board_state;
        $piece = $board[$from] ?? null;
        if (!$piece) {
            return ['valid' => false, 'error' => 'No piece at source'];
        }
        if ($piece['color'] !== $this->current_turn) {
            return ['valid' => false, 'error' => 'Not your piece'];
        }

        $legalMoves = $this->getLegalMoves($from);
        $move = null;
        foreach ($legalMoves as $m) {
            if (end($m['path']) === $to) {
                $move = $m;
                break;
            }
        }

        if (!$move) {
            return ['valid' => false, 'error' => 'Illegal move'];
        }

        return ['valid' => true, 'move' => $move];
    }

    // ── Apply a validated move ───────────────────────────────────────────
    public function applyMove(array $move): bool
    {
        $board = $this->board_state;
        $from = $move['path'][0];
        $to = end($move['path']);
        $piece = $board[$from];

        $board[$from] = null;
        foreach ($move['captured'] as $capIdx) {
            $board[$capIdx] = null;
        }

        [$destRow] = self::indexToRowCol($to);
        $crowned = false;
        if (!$piece['isKing']) {
            if (($piece['color'] === 'red' && $destRow === 0) ||
                ($piece['color'] === 'black' && $destRow === 7)) {
                $piece['isKing'] = true;
                $crowned = true;
            }
        }

        $board[$to] = $piece;
        $nextTurn = $this->current_turn === 'red' ? 'black' : 'red';
        $nextUserId = $this->current_turn === 'red'
            ? $this->player2_id
            : $this->player1_id;

        $this->board_state = $board;
        $this->current_turn = $nextTurn;
        $this->current_turn_user_id = $nextUserId;
        $this->last_move_from = $from;
        $this->last_move_to = $to;
        $this->last_captured = $move['captured'];
        $this->last_crowned = $crowned;
        $this->move_count++;

        // Check game over
        if ($this->isGameOver()) {
            $winner = $this->getWinner();
            if ($winner) {
                $this->status = 'completed';
                $this->winner_id = $winner === 'red' ? $this->player1_id : $this->player2_id;
                $this->winner_name = $winner === 'red' ? $this->player1_name : $this->player2_name;
                $this->ended_at = now();
            }
        }

        $this->save();
        return $crowned;
    }

    // ── Legal move generation ────────────────────────────────────────────
    public function getLegalMoves(int $idx): array
    {
        $board = $this->board_state;
        $piece = $board[$idx] ?? null;
        if (!$piece || $piece['color'] !== $this->current_turn) return [];

        $jumps = $this->getJumps($idx, $piece, $board, [$idx], []);
        if ($this->anyJumpExists()) {
            return $jumps;
        }
        return $this->getSimpleMoves($idx, $piece, $board);
    }

    public function getAllLegalMoves(): array
    {
        $board = $this->board_state;
        $jumpMoves = [];
        $simpleMoves = [];

        for ($i = 0; $i < 32; $i++) {
            $piece = $board[$i] ?? null;
            if (!$piece || $piece['color'] !== $this->current_turn) continue;

            $jumps = $this->getJumps($i, $piece, $board, [$i], []);
            $jumpMoves = array_merge($jumpMoves, $jumps);
            if (empty($jumpMoves)) {
                $simpleMoves = array_merge($simpleMoves, $this->getSimpleMoves($i, $piece, $board));
            }
        }

        return !empty($jumpMoves) ? $jumpMoves : $simpleMoves;
    }

    private function anyJumpExists(): bool
    {
        $board = $this->board_state;
        for ($i = 0; $i < 32; $i++) {
            $piece = $board[$i] ?? null;
            if (!$piece || $piece['color'] !== $this->current_turn) continue;
            if (!empty($this->getJumps($i, $piece, $board, [$i], []))) return true;
        }
        return false;
    }

    private function getSimpleMoves(int $idx, array $piece, array $board): array
    {
        $moves = [];
        [$row, $col] = self::indexToRowCol($idx);
        $dirs = $this->moveDirections($piece);

        foreach ($dirs as [$dr, $dc]) {
            $nr = $row + $dr;
            $nc = $col + $dc;
            $ni = self::rowColToIndex($nr, $nc);
            if ($ni < 0 || $board[$ni] !== null) continue;
            $moves[] = ['path' => [$idx, $ni], 'captured' => []];
        }
        return $moves;
    }

    private function getJumps(int $idx, array $piece, array $board, array $path, array $captured): array
    {
        [$row, $col] = self::indexToRowCol($idx);
        $dirs = $piece['isKing']
            ? [[-1,-1],[-1,1],[1,-1],[1,1]]
            : ($piece['color'] === 'red' ? [[-1,-1],[-1,1]] : [[1,-1],[1,1]]);
        $results = [];

        foreach ($dirs as [$dr, $dc]) {
            $midR = $row + $dr;
            $midC = $col + $dc;
            $midIdx = self::rowColToIndex($midR, $midC);
            if ($midIdx < 0 || in_array($midIdx, $captured)) continue;

            $midPiece = $board[$midIdx] ?? null;
            if (!$midPiece || $midPiece['color'] === $piece['color']) continue;

            $landR = $row + $dr * 2;
            $landC = $col + $dc * 2;
            $landIdx = self::rowColToIndex($landR, $landC);
            if ($landIdx < 0) continue;
            if ($board[$landIdx] !== null && $landIdx !== $idx) continue;

            $newBoard = $board;
            $newBoard[$idx] = null;
            $newBoard[$midIdx] = null;
            $shouldCrown = !$piece['isKing'] &&
                (($piece['color'] === 'red' && $landR === 0) ||
                 ($piece['color'] === 'black' && $landR === 7));
            $landPiece = $shouldCrown ? array_merge($piece, ['isKing' => true]) : $piece;
            $newBoard[$landIdx] = $landPiece;

            $newPath = array_merge($path, [$landIdx]);
            $newCaptured = array_merge($captured, [$midIdx]);

            $continuations = ($shouldCrown && !$piece['isKing'])
                ? []
                : $this->getJumps($landIdx, $landPiece, $newBoard, $newPath, $newCaptured);

            if (empty($continuations)) {
                $results[] = ['path' => $newPath, 'captured' => $newCaptured];
            } else {
                $results = array_merge($results, $continuations);
            }
        }
        return $results;
    }

    private function moveDirections(array $piece): array
    {
        if ($piece['isKing']) return [[-1,-1],[-1,1],[1,-1],[1,1]];
        return $piece['color'] === 'red' ? [[-1,-1],[-1,1]] : [[1,-1],[1,1]];
    }

    private function isGameOver(): bool
    {
        return empty($this->getAllLegalMoves()) || $this->noPieces($this->current_turn);
    }

    private function noPieces(string $color): bool
    {
        foreach ($this->board_state as $p) {
            if ($p && $p['color'] === $color) return false;
        }
        return true;
    }

    private function getWinner(): ?string
    {
        $board = $this->board_state;
        $hasRed = false;
        $hasBlack = false;
        foreach ($board as $p) {
            if ($p && $p['color'] === 'red') $hasRed = true;
            if ($p && $p['color'] === 'black') $hasBlack = true;
        }
        if (!$hasRed) return 'black';
        if (!$hasBlack) return 'red';
        if (empty($this->getAllLegalMoves())) {
            return $this->current_turn === 'red' ? 'black' : 'red';
        }
        return null;
    }

    // ── API format ───────────────────────────────────────────────────────
    public function toApiFormat(): array
    {
        $board = $this->board_state;
        $redPieces = 0; $blackPieces = 0;
        $redKings = 0; $blackKings = 0;
        foreach ($board as $p) {
            if (!$p) continue;
            if ($p['color'] === 'red') { $redPieces++; if ($p['isKing']) $redKings++; }
            else { $blackPieces++; if ($p['isKing']) $blackKings++; }
        }

        return [
            'id' => $this->id,
            'session_code' => $this->session_code,
            'status' => $this->status,
            'player1_id' => $this->player1_id,
            'player1_name' => $this->player1_name,
            'player2_id' => $this->player2_id,
            'player2_name' => $this->player2_name,
            'board_state' => $board,
            'current_turn' => $this->current_turn,
            'current_turn_user_id' => $this->current_turn_user_id,
            'last_move_from' => $this->last_move_from,
            'last_move_to' => $this->last_move_to,
            'last_captured' => $this->last_captured ?? [],
            'last_crowned' => $this->last_crowned,
            'winner_id' => $this->winner_id,
            'winner_name' => $this->winner_name,
            'move_count' => $this->move_count,
            'red_pieces' => $redPieces,
            'black_pieces' => $blackPieces,
            'red_kings' => $redKings,
            'black_kings' => $blackKings,
            'chat_head_id' => $this->chat_head_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function chatMessages()
    {
        return $this->hasMany(\App\Models\CheckersChatMessage::class, 'session_id');
    }
}
