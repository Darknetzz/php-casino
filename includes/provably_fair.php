<?php
/**
 * Provably Fair System
 * 
 * This system ensures that game results are fair and verifiable:
 * 1. Server generates a seed and its hash before the round
 * 2. Users can verify the hash matches the seed after the round
 * 3. Results are derived deterministically from the combined seeds
 * 4. Admins can predict results by knowing the server seed
 */

class ProvablyFair {
    /**
     * Generate a random server seed
     */
    public static function generateServerSeed() {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }
    
    /**
     * Hash a server seed (SHA256)
     */
    public static function hashServerSeed($serverSeed) {
        return hash('sha256', $serverSeed);
    }
    
    /**
     * Generate a result number for roulette (0-36) from combined seeds
     */
    public static function generateRouletteResult($serverSeed, $clientSeed = '') {
        $combined = $serverSeed . $clientSeed;
        $hash = hash('sha256', $combined);
        
        // Use first 8 hex characters to generate a number 0-36
        $hex = substr($hash, 0, 8);
        $decimal = hexdec($hex);
        
        // Convert to 0-36 range
        $result = $decimal % 37;
        
        return $result;
    }
    
    /**
     * Generate a crash point from combined seeds
     * Uses the same distribution formula as the client-side version
     */
    public static function generateCrashPoint($serverSeed, $clientSeed = '', $distributionParam = 0.99) {
        $combined = $serverSeed . $clientSeed;
        $hash = hash('sha256', $combined);
        
        // Use first 8 hex characters to generate a random value 0-1
        $hex = substr($hash, 0, 8);
        $decimal = hexdec($hex);
        $r = ($decimal / 0xFFFFFFFF); // Normalize to 0-1
        
        // Use the same formula as client-side
        $param = max(0.01, min(0.999, $distributionParam));
        $crash = max(1.00, 1 + ($r * $param) / (1 - $r * $param));
        
        // Round to 2 decimal places
        return round($crash, 2);
    }
    
    /**
     * Verify that a server seed hash matches the actual seed
     */
    public static function verifyServerSeed($serverSeed, $serverSeedHash) {
        return self::hashServerSeed($serverSeed) === $serverSeedHash;
    }
    
    /**
     * Get the next round number for a game
     */
    public static function getNextRoundNumber($db, $game) {
        $table = $game === 'roulette' ? 'roulette_rounds' : 'crash_rounds';
        $stmt = $db->getConnection()->prepare("SELECT MAX(round_number) as max_round FROM $table");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxRound = intval($result['max_round'] ?? 0);
        return $maxRound + 1;
    }
}
?>
