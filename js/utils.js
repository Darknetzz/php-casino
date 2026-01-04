/**
 * Utility Functions for Casino Games
 * 
 * Shared JavaScript utility functions
 */

/**
 * Get the color for a roulette number (text color)
 * Matches PHP function getRouletteNumberColor() in includes/functions.php
 * 
 * @param {number} number - The roulette number (0-36)
 * @returns {string} Hex color code
 */
function getRouletteNumberColor(number) {
    const num = parseInt(number, 10);
    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    const isDarkMode = document.body.classList.contains('dark-mode');
    
    if (num === 0) {
        return '#28a745'; // Green
    } else if (redNumbers.includes(num)) {
        return '#dc3545'; // Red
    } else {
        return isDarkMode ? '#e0e0e0' : '#333333'; // Light gray in dark mode, dark gray in light mode
    }
}

/**
 * Get background and text colors for a roulette number (for circles/badges)
 * Matches PHP function getRouletteNumberColors() in includes/functions.php
 * 
 * @param {number} number - The roulette number (0-36)
 * @returns {Object} Object with 'bg' and 'text' color properties
 */
function getRouletteNumberColors(number) {
    const num = parseInt(number, 10);
    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    const isDarkMode = document.body.classList.contains('dark-mode');
    
    if (num === 0) {
        return { bg: '#28a745', text: '#ffffff' }; // Green
    } else if (redNumbers.includes(num)) {
        return { bg: '#dc3545', text: '#ffffff' }; // Red
    } else {
        return {
            bg: isDarkMode ? '#2c2c2c' : '#1a1a1a',
            text: '#ffffff'
        }; // Black
    }
}

/**
 * Get the color name for a roulette number
 * Matches PHP function getRouletteNumberColorName() in includes/functions.php
 * 
 * @param {number} number - The roulette number (0-36)
 * @returns {string} Color name ('green', 'red', or 'black')
 */
function getRouletteNumberColorName(number) {
    const num = parseInt(number, 10);
    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    if (num === 0) {
        return 'green';
    } else if (redNumbers.includes(num)) {
        return 'red';
    } else {
        return 'black';
    }
}
