<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Ludo Game Session Model
 * 
 * Tracks online Ludo game state between 2-4 players
 * Each player has 4 pieces that move around the board
 */
class LudoSession extends Model
{
    use HasFactory;

    protected $table = 'ludo_sessions';

    protected $fillable = [
        'session_code',
        'status',
        'game_type', // '2_player', '4_player'
        
        // Player 1 (Red - Host)
        'player1_id',
        'player1_name',
        'player1_avatar',
        'player1_pieces', // JSON: positions of 4 pieces
        'player1_finished_count', // How many pieces reached home
        
        // Player 2 (Green)
        'player2_id',
        'player2_name',
        'player2_avatar',
        'player2_pieces',
        'player2_finished_count',
        
        // Player 3 (Yellow) - Optional for 4 player
        'player3_id',
        'player3_name',
        'player3_avatar',
        'player3_pieces',
        'player3_finished_count',
        
        // Player 4 (Blue) - Optional for 4 player
        'player4_id',
        'player4_name',
        'player4_avatar',
        'player4_pieces',
        'player4_finished_count',
        
        // Game state
        'current_turn_player', // 1, 2, 3, or 4
        'current_turn_user_id',
        'last_dice_roll',
        'consecutive_sixes', // Track for 3-six rule
        'can_roll_again', // After rolling 6 or capturing
        'must_move_piece', // After rolling, must move (or pass if no valid move)
        
        // Actions
        'last_action',
        'last_action_player',
        'last_captured_piece', // JSON: info about last capture
        
        // Timing
        'turn_started_at',
        'winner_player', // 1, 2, 3, or 4
        'winner_user_id',
        'rankings', // JSON: [first, second, third, fourth]
        
        // Polling
        'player1_last_poll',
        'player2_last_poll',
        'player3_last_poll',
        'player4_last_poll',
        
        // Timestamps
        'started_at',
        'ended_at',
        'expires_at',
    ];

    protected $casts = [
        'player1_pieces' => 'array',
        'player2_pieces' => 'array',
        'player3_pieces' => 'array',
        'player4_pieces' => 'array',
        'last_captured_piece' => 'array',
        'rankings' => 'array',
        'player1_last_poll' => 'datetime',
        'player2_last_poll' => 'datetime',
        'player3_last_poll' => 'datetime',
        'player4_last_poll' => 'datetime',
        'turn_started_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
        'can_roll_again' => 'boolean',
        'must_move_piece' => 'boolean',
    ];

    /**
     * Board configuration constants
     * 
     * Standard Ludo board:
     * - Main track: 52 squares (0-51)
     * - Home column: 6 squares per player (H0-H5)
     * - Yard: Starting area for pieces (-1 = in yard)
     * - Home: Center finish square (99 = home/finished)
     */
    const BOARD_SIZE = 52; // Main track squares
    const HOME_COLUMN_SIZE = 6;
    const YARD_POSITION = -1;
    const HOME_POSITION = 99;

    // Starting positions on main track for each player color
    const START_POSITIONS = [
        1 => 0,   // Red starts at 0
        2 => 13,  // Green starts at 13
        3 => 26,  // Yellow starts at 26
        4 => 39,  // Blue starts at 39
    ];

    // Entry to home column positions
    const HOME_ENTRY_POSITIONS = [
        1 => 50,  // Red enters home column after 50
        2 => 11,  // Green enters home column after 11
        3 => 24,  // Yellow enters home column after 24
        4 => 37,  // Blue enters home column after 37
    ];

    // Safe squares (can't be captured)
    const SAFE_SQUARES = [0, 8, 13, 21, 26, 34, 39, 47]; // Starting squares

    // Relationships
    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function player3()
    {
        return $this->belongsTo(User::class, 'player3_id');
    }

    public function player4()
    {
        return $this->belongsTo(User::class, 'player4_id');
    }

    /**
     * Check if user is a player in this game
     */
    public function isPlayer($userId)
    {
        return $this->player1_id == $userId ||
               $this->player2_id == $userId ||
               $this->player3_id == $userId ||
               $this->player4_id == $userId;
    }

    /**
     * Get player number (1-4) for a user ID
     */
    public function getPlayerNumber($userId)
    {
        if ($this->player1_id == $userId) return 1;
        if ($this->player2_id == $userId) return 2;
        if ($this->player3_id == $userId) return 3;
        if ($this->player4_id == $userId) return 4;
        return 0;
    }

    /**
     * Get pieces for a player number
     */
    public function getPiecesForPlayer($playerNum)
    {
        $field = "player{$playerNum}_pieces";
        $pieces = $this->$field ?? $this->getDefaultPieces();
        
        // CRITICAL: Sanitize and validate pieces to prevent disappearing pieces bug
        return $this->sanitizePieces($pieces);
    }

    /**
     * Sanitize pieces array to ensure all 4 pieces have valid positions
     * This prevents pieces from disappearing due to corrupted data
     */
    public function sanitizePieces($pieces)
    {
        $sanitized = [];
        $existingIds = [];
        
        // Process existing pieces
        if (is_array($pieces)) {
            foreach ($pieces as $piece) {
                if (!is_array($piece)) continue;
                
                $id = $piece['id'] ?? count($sanitized);
                if (isset($existingIds[$id])) continue; // Skip duplicates
                
                $position = $piece['position'] ?? self::YARD_POSITION;
                $inHomeColumn = $piece['in_home_column'] ?? false;
                $homeColumnPos = $piece['home_column_position'] ?? -1;
                
                // Validate position is in expected range
                // Valid: -1 (yard), 0-51 (track), or 99 (home)
                // Home column is encoded via in_home_column flag
                if ($position != self::YARD_POSITION && 
                    $position != self::HOME_POSITION &&
                    ($position < 0 || $position >= self::BOARD_SIZE) &&
                    !$inHomeColumn) {
                    // Invalid position - log and reset to yard
                    \Log::warning("Ludo: Invalid piece position detected", [
                        'piece' => $piece,
                        'session_id' => $this->id,
                    ]);
                    $position = self::YARD_POSITION;
                    $inHomeColumn = false;
                    $homeColumnPos = -1;
                }
                
                $sanitized[] = [
                    'id' => $id,
                    'position' => $position,
                    'in_home_column' => $inHomeColumn,
                    'home_column_position' => $homeColumnPos,
                ];
                $existingIds[$id] = true;
            }
        }
        
        // Ensure exactly 4 pieces (add missing ones in yard)
        while (count($sanitized) < 4) {
            $newId = count($sanitized);
            while (isset($existingIds[$newId])) $newId++;
            
            \Log::warning("Ludo: Missing piece detected, adding default", [
                'piece_id' => $newId,
                'session_id' => $this->id,
            ]);
            
            $sanitized[] = [
                'id' => $newId,
                'position' => self::YARD_POSITION,
                'in_home_column' => false,
                'home_column_position' => -1,
            ];
            $existingIds[$newId] = true;
        }
        
        return $sanitized;
    }

    /**
     * Get default pieces (all in yard)
     */
    public static function getDefaultPieces()
    {
        return [
            ['id' => 0, 'position' => self::YARD_POSITION, 'in_home_column' => false, 'home_column_position' => -1],
            ['id' => 1, 'position' => self::YARD_POSITION, 'in_home_column' => false, 'home_column_position' => -1],
            ['id' => 2, 'position' => self::YARD_POSITION, 'in_home_column' => false, 'home_column_position' => -1],
            ['id' => 3, 'position' => self::YARD_POSITION, 'in_home_column' => false, 'home_column_position' => -1],
        ];
    }

    /**
     * Initialize a new 2-player Ludo game
     */
    public static function createTwoPlayerGame($player1Id, $player2Id)
    {
        $player1 = User::find($player1Id);
        $player2 = User::find($player2Id);

        $session = self::create([
            'session_code' => 'LUDO-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'status' => 'playing',
            'game_type' => '2_player',
            
            'player1_id' => $player1Id,
            'player1_name' => $player1->name ?? 'Player 1',
            'player1_avatar' => $player1->avatar ?? '',
            'player1_pieces' => self::getDefaultPieces(),
            'player1_finished_count' => 0,
            
            'player2_id' => $player2Id,
            'player2_name' => $player2->name ?? 'Player 2',
            'player2_avatar' => $player2->avatar ?? '',
            'player2_pieces' => self::getDefaultPieces(),
            'player2_finished_count' => 0,
            
            'current_turn_player' => 1,
            'current_turn_user_id' => $player1Id,
            'last_dice_roll' => 0,
            'consecutive_sixes' => 0,
            'can_roll_again' => false,
            'must_move_piece' => false,
            
            'started_at' => now(),
            'turn_started_at' => now(),
        ]);

        return $session;
    }

    /**
     * Roll the dice
     * Returns the dice value (1-6)
     */
    public function rollDice($userId)
    {
        // Verify it's this player's turn
        if ($this->current_turn_user_id != $userId) {
            return ['success' => false, 'message' => 'Not your turn'];
        }

        // Check if already rolled and must move
        if ($this->must_move_piece) {
            return ['success' => false, 'message' => 'You must move a piece first'];
        }

        // Roll the dice with better randomness
        // Use random_int for cryptographically secure random number generation
        try {
            $diceValue = random_int(1, 6);
        } catch (\Exception $e) {
            // Fallback to mt_rand if random_int fails (shouldn't happen)
            $diceValue = mt_rand(1, 6);
        }
        
        $this->last_dice_roll = $diceValue;

        // Track consecutive sixes
        if ($diceValue == 6) {
            $this->consecutive_sixes++;
            
            // Three sixes in a row = forfeit turn
            if ($this->consecutive_sixes >= 3) {
                $this->consecutive_sixes = 0;
                $this->can_roll_again = false;
                $this->must_move_piece = false;
                $this->nextTurn();
                $this->save();
                
                return [
                    'success' => true,
                    'dice' => $diceValue,
                    'message' => 'Three sixes! Turn forfeited.',
                    'turn_ended' => true,
                ];
            }
        } else {
            $this->consecutive_sixes = 0;
        }

        // Check if any valid moves exist
        $playerNum = $this->getPlayerNumber($userId);
        $validMoves = $this->getValidMoves($playerNum, $diceValue);

        if (empty($validMoves)) {
            // No valid moves - end turn (or allow roll again if got 6)
            if ($diceValue == 6) {
                $this->can_roll_again = true;
                $this->must_move_piece = false;
            } else {
                $this->nextTurn();
            }
            $this->save();
            
            return [
                'success' => true,
                'dice' => $diceValue,
                'message' => 'No valid moves available',
                'valid_moves' => [],
                'can_roll_again' => $this->can_roll_again,
            ];
        }

        $this->must_move_piece = true;
        $this->can_roll_again = ($diceValue == 6);
        $this->save();

        return [
            'success' => true,
            'dice' => $diceValue,
            'valid_moves' => $validMoves,
            'can_roll_again' => $this->can_roll_again,
        ];
    }

    /**
     * Get valid moves for a player given a dice roll
     */
    public function getValidMoves($playerNum, $diceValue)
    {
        $pieces = $this->getPiecesForPlayer($playerNum);
        $validMoves = [];

        foreach ($pieces as $piece) {
            $moveResult = $this->calculateMove($playerNum, $piece, $diceValue);
            if ($moveResult['valid']) {
                $validMoves[] = [
                    'piece_id' => $piece['id'],
                    'from_position' => $piece['position'],
                    'to_position' => $moveResult['new_position'],
                    'enters_home_column' => $moveResult['enters_home_column'] ?? false,
                    'reaches_home' => $moveResult['reaches_home'] ?? false,
                    'captures' => $moveResult['captures'] ?? null,
                ];
            }
        }

        return $validMoves;
    }

    /**
     * Calculate move result for a piece
     */
    public function calculateMove($playerNum, $piece, $diceValue)
    {
        $position = $piece['position'];
        $inHomeColumn = $piece['in_home_column'] ?? false;
        $homeColumnPos = $piece['home_column_position'] ?? -1;

        // Piece in yard - needs 6 to enter
        if ($position == self::YARD_POSITION) {
            if ($diceValue == 6) {
                return [
                    'valid' => true,
                    'new_position' => self::START_POSITIONS[$playerNum],
                    'in_home_column' => false,
                ];
            }
            return ['valid' => false];
        }

        // Piece already finished
        if ($position == self::HOME_POSITION) {
            return ['valid' => false];
        }

        // Piece in home column
        if ($inHomeColumn) {
            $newHomePos = $homeColumnPos + $diceValue;
            
            // Exact roll needed to reach home
            if ($newHomePos == self::HOME_COLUMN_SIZE) {
                return [
                    'valid' => true,
                    'new_position' => self::HOME_POSITION,
                    'in_home_column' => true,
                    'home_column_position' => self::HOME_COLUMN_SIZE,
                    'reaches_home' => true,
                ];
            }
            
            // Can't overshoot
            if ($newHomePos > self::HOME_COLUMN_SIZE) {
                return ['valid' => false];
            }
            
            return [
                'valid' => true,
                'new_position' => $position, // Keep track position for reference
                'in_home_column' => true,
                'home_column_position' => $newHomePos,
            ];
        }

        // Normal track movement
        $homeEntry = self::HOME_ENTRY_POSITIONS[$playerNum];
        $startPos = self::START_POSITIONS[$playerNum];
        
        // Calculate steps considering circular board
        $stepsFromStart = ($position - $startPos + self::BOARD_SIZE) % self::BOARD_SIZE;
        $newStepsFromStart = $stepsFromStart + $diceValue;
        
        // Check if entering home column
        $stepsToHomeEntry = ($homeEntry - $startPos + self::BOARD_SIZE) % self::BOARD_SIZE;
        
        if ($stepsFromStart <= $stepsToHomeEntry && $newStepsFromStart > $stepsToHomeEntry) {
            // Entering home column
            $stepsIntoHomeColumn = $newStepsFromStart - $stepsToHomeEntry - 1;
            
            if ($stepsIntoHomeColumn >= self::HOME_COLUMN_SIZE) {
                // Would overshoot home
                if ($stepsIntoHomeColumn == self::HOME_COLUMN_SIZE) {
                    // Exact roll to home!
                    return [
                        'valid' => true,
                        'new_position' => self::HOME_POSITION,
                        'in_home_column' => true,
                        'home_column_position' => self::HOME_COLUMN_SIZE,
                        'reaches_home' => true,
                    ];
                }
                return ['valid' => false];
            }
            
            return [
                'valid' => true,
                'new_position' => $position, // Keep for reference
                'in_home_column' => true,
                'home_column_position' => $stepsIntoHomeColumn,
                'enters_home_column' => true,
            ];
        }

        // Normal movement on track
        $newPosition = ($position + $diceValue) % self::BOARD_SIZE;
        
        // Check for captures
        $captures = $this->checkCapture($playerNum, $newPosition);

        return [
            'valid' => true,
            'new_position' => $newPosition,
            'in_home_column' => false,
            'captures' => $captures,
        ];
    }

    /**
     * Check if moving to a position captures an opponent piece
     */
    public function checkCapture($movingPlayerNum, $position)
    {
        // Can't capture on safe squares
        if (in_array($position, self::SAFE_SQUARES)) {
            return null;
        }

        $maxPlayers = ($this->game_type == '4_player') ? 4 : 2;
        
        for ($p = 1; $p <= $maxPlayers; $p++) {
            if ($p == $movingPlayerNum) continue;
            if ($p > 2 && $this->game_type == '2_player') continue;
            
            $pieces = $this->getPiecesForPlayer($p);
            foreach ($pieces as $piece) {
                if ($piece['position'] == $position && 
                    !($piece['in_home_column'] ?? false) &&
                    $piece['position'] != self::YARD_POSITION) {
                    return [
                        'player' => $p,
                        'piece_id' => $piece['id'],
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Move a piece
     */
    public function movePiece($userId, $pieceId)
    {
        // Verify turn
        if ($this->current_turn_user_id != $userId) {
            return ['success' => false, 'message' => 'Not your turn'];
        }

        // Verify must move
        if (!$this->must_move_piece) {
            return ['success' => false, 'message' => 'Roll the dice first'];
        }

        $playerNum = $this->getPlayerNumber($userId);
        $pieces = $this->getPiecesForPlayer($playerNum);
        $diceValue = $this->last_dice_roll;

        // Find the piece
        $pieceIndex = null;
        $piece = null;
        foreach ($pieces as $index => $p) {
            if ($p['id'] == $pieceId) {
                $pieceIndex = $index;
                $piece = $p;
                break;
            }
        }

        if ($piece === null) {
            return ['success' => false, 'message' => 'Piece not found'];
        }

        // Calculate move
        $moveResult = $this->calculateMove($playerNum, $piece, $diceValue);
        
        if (!$moveResult['valid']) {
            return ['success' => false, 'message' => 'Invalid move for this piece'];
        }

        // Apply the move
        $pieces[$pieceIndex]['position'] = $moveResult['new_position'];
        $pieces[$pieceIndex]['in_home_column'] = $moveResult['in_home_column'] ?? false;
        $pieces[$pieceIndex]['home_column_position'] = $moveResult['home_column_position'] ?? -1;

        // Handle capture
        $captured = false;
        if (!empty($moveResult['captures'])) {
            $capturedPlayer = $moveResult['captures']['player'];
            $capturedPieceId = $moveResult['captures']['piece_id'];
            
            $capturedPieces = $this->getPiecesForPlayer($capturedPlayer);
            foreach ($capturedPieces as $i => $cp) {
                if ($cp['id'] == $capturedPieceId) {
                    $capturedPieces[$i]['position'] = self::YARD_POSITION;
                    $capturedPieces[$i]['in_home_column'] = false;
                    $capturedPieces[$i]['home_column_position'] = -1;
                    break;
                }
            }
            
            $this->{"player{$capturedPlayer}_pieces"} = $capturedPieces;
            $this->last_captured_piece = $moveResult['captures'];
            $captured = true;
        }

        // Update pieces
        $this->{"player{$playerNum}_pieces"} = $pieces;

        // Check if piece reached home
        $reachedHome = $moveResult['reaches_home'] ?? false;
        if ($reachedHome) {
            $this->{"player{$playerNum}_finished_count"}++;
            
            // Check win condition
            if ($this->{"player{$playerNum}_finished_count"} >= 4) {
                $this->status = 'completed';
                $this->winner_player = $playerNum;
                $this->winner_user_id = $userId;
                $this->ended_at = now();
                $this->save();

                return [
                    'success' => true,
                    'message' => 'You won!',
                    'game_over' => true,
                    'winner' => $userId,
                ];
            }
        }

        // Build action description
        $actionDesc = "Moved piece from {$piece['position']}";
        if ($captured) {
            $actionDesc .= " and captured opponent's piece!";
            $this->can_roll_again = true; // Bonus roll for capture
        }
        if ($reachedHome) {
            $actionDesc .= " - Piece reached home!";
            $this->can_roll_again = true; // Bonus roll for reaching home
        }
        
        $this->last_action = $actionDesc;
        $this->last_action_player = $playerNum;
        $this->must_move_piece = false;

        // Determine if turn continues
        if ($this->can_roll_again && $this->consecutive_sixes < 3) {
            // Player gets another roll
            $this->save();
            return [
                'success' => true,
                'message' => $actionDesc,
                'roll_again' => true,
                'captured' => $captured,
                'reached_home' => $reachedHome,
            ];
        }

        // End turn
        $this->nextTurn();
        $this->save();

        return [
            'success' => true,
            'message' => $actionDesc,
            'turn_ended' => true,
            'captured' => $captured,
            'reached_home' => $reachedHome,
        ];
    }

    /**
     * Advance to next player's turn
     */
    public function nextTurn()
    {
        $maxPlayers = ($this->game_type == '4_player') ? 4 : 2;
        
        // In 2-player mode, alternate between 1 and 2
        if ($this->game_type == '2_player') {
            $this->current_turn_player = ($this->current_turn_player == 1) ? 2 : 1;
        } else {
            // 4-player mode
            $nextPlayer = ($this->current_turn_player % $maxPlayers) + 1;
            
            // Skip players who have finished or disconnected
            $attempts = 0;
            while ($attempts < $maxPlayers) {
                $playerId = $this->{"player{$nextPlayer}_id"};
                if ($playerId && $this->{"player{$nextPlayer}_finished_count"} < 4) {
                    break;
                }
                $nextPlayer = ($nextPlayer % $maxPlayers) + 1;
                $attempts++;
            }
            
            $this->current_turn_player = $nextPlayer;
        }

        $this->current_turn_user_id = $this->{"player{$this->current_turn_player}_id"};
        $this->turn_started_at = now();
        $this->last_dice_roll = 0;
        $this->consecutive_sixes = 0;
        $this->can_roll_again = false;
        $this->must_move_piece = false;
    }

    /**
     * Player leaves/forfeits the game
     */
    public function playerLeave($userId)
    {
        $playerNum = $this->getPlayerNumber($userId);
        
        if ($playerNum == 0) {
            return ['success' => false, 'message' => 'You are not in this game'];
        }

        // In 2-player, other player wins
        if ($this->game_type == '2_player') {
            $winnerNum = ($playerNum == 1) ? 2 : 1;
            $this->status = 'cancelled'; // Use cancelled to indicate forfeit
            $this->winner_player = $winnerNum;
            $this->winner_user_id = $this->{"player{$winnerNum}_id"};
            $this->ended_at = now();
            $this->last_action = "Player forfeited - Opponent wins!";
            $this->last_action_player = $playerNum;
        } else {
            // 4-player: mark as forfeited, continue game
            $this->{"player{$playerNum}_id"} = null;
            $this->last_action = "Player {$playerNum} left the game";
            $this->last_action_player = $playerNum;
            
            // Check if game should end
            $activePlayers = 0;
            $lastActivePlayer = 0;
            for ($p = 1; $p <= 4; $p++) {
                if ($this->{"player{$p}_id"}) {
                    $activePlayers++;
                    $lastActivePlayer = $p;
                }
            }
            
            if ($activePlayers <= 1) {
                $this->status = 'cancelled';
                $this->winner_player = $lastActivePlayer;
                $this->winner_user_id = $this->{"player{$lastActivePlayer}_id"};
                $this->ended_at = now();
            } else if ($this->current_turn_player == $playerNum) {
                $this->nextTurn();
            }
        }
        
        $this->save();
        return ['success' => true, 'message' => 'Left the game'];
    }

    /**
     * Format session for API response
     */
    public function toApiFormat($userId = null)
    {
        $playerNum = $userId ? $this->getPlayerNumber($userId) : 0;
        $isMyTurn = $userId && $this->current_turn_user_id == $userId;

        return [
            'id' => $this->id,
            'session_code' => $this->session_code,
            'status' => $this->status,
            'game_type' => $this->game_type,
            
            'current_turn_player' => $this->current_turn_player,
            'current_turn_user_id' => $this->current_turn_user_id,
            'is_my_turn' => $isMyTurn,
            'my_player_number' => $playerNum,
            
            'last_dice_roll' => $this->last_dice_roll,
            'can_roll_again' => $this->can_roll_again,
            'must_move_piece' => $this->must_move_piece,
            'consecutive_sixes' => $this->consecutive_sixes,
            
            'last_action' => $this->last_action,
            'last_action_player' => $this->last_action_player,
            'last_captured_piece' => $this->last_captured_piece,
            
            'players' => [
                1 => [
                    'id' => $this->player1_id,
                    'name' => $this->player1_name,
                    'avatar' => $this->player1_avatar,
                    'pieces' => $this->getPiecesForPlayer(1), // Sanitized pieces
                    'finished_count' => $this->player1_finished_count,
                    'color' => 'red',
                ],
                2 => [
                    'id' => $this->player2_id,
                    'name' => $this->player2_name,
                    'avatar' => $this->player2_avatar,
                    'pieces' => $this->getPiecesForPlayer(2), // Sanitized pieces
                    'finished_count' => $this->player2_finished_count,
                    'color' => 'green',
                ],
            ],
            
            'winner_player' => $this->winner_player,
            'winner_user_id' => $this->winner_user_id,
            
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
        ];
    }
}
