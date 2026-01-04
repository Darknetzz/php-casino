<?php
/**
 * Utility Functions
 * 
 * Centralized utility functions for the casino application
 */

/**
 * Get the color for a roulette number
 * Returns the background color hex code for the number
 * 
 * @param int $number The roulette number (0-36)
 * @return string Hex color code
 */
function getRouletteNumberColor($number) {
    $number = intval($number); // Ensure it's an integer
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    if ($number === 0) {
        return '#28a745'; // Green
    } elseif (in_array($number, $redNumbers, true)) {
        return '#dc3545'; // Red
    } else {
        return '#1a1a1a'; // Black
    }
}

/**
 * Get the color name for a roulette number
 * Returns 'green', 'red', or 'black'
 * 
 * @param int $number The roulette number (0-36)
 * @return string Color name
 */
function getRouletteNumberColorName($number) {
    $number = intval($number);
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    if ($number === 0) {
        return 'green';
    } elseif (in_array($number, $redNumbers, true)) {
        return 'red';
    } else {
        return 'black';
    }
}

/**
 * Get background and text colors for a roulette number
 * Useful for creating colored circles/badges
 * 
 * @param int $number The roulette number (0-36)
 * @param bool $darkMode Whether dark mode is active (for black numbers)
 * @return array Array with 'bg' and 'text' color keys
 */
function getRouletteNumberColors($number, $darkMode = false) {
    $number = intval($number);
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    if ($number === 0) {
        return ['bg' => '#28a745', 'text' => '#ffffff']; // Green
    } elseif (in_array($number, $redNumbers, true)) {
        return ['bg' => '#dc3545', 'text' => '#ffffff']; // Red
    } else {
        // Black - adjust for dark mode
        return [
            'bg' => $darkMode ? '#2c2c2c' : '#1a1a1a',
            'text' => '#ffffff'
        ];
    }
}
