<?php
// includes/functions.php

/**
 * Formate une date en français
 * @param string $date Date à formater (format MySQL ou timestamp)
 * @param string $format Format de sortie
 * @return string Date formatée
 */
function format_date_fr($date = null, $format = 'full') {
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if (!$timestamp) $timestamp = time();
    
    // Formats prédéfinis
    $formats = [
        'full' => 'EEEE dd MMMM YYYY',
        'long' => 'dd MMMM YYYY',
        'medium' => 'dd MMM YYYY',
        'short' => 'dd/MM/YYYY',
        'time' => 'HH:mm',
        'datetime' => 'dd/MM/YYYY HH:mm'
    ];
    
    $pattern = $formats[$format] ?? $format;
    
    // Essayer IntlDateFormatter d'abord
    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            'Europe/Paris',
            IntlDateFormatter::GREGORIAN,
            $pattern
        );
        return $formatter->format($timestamp);
    }
    
    // Fallback si Intl n'est pas disponible
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    
    $date_obj = new DateTime("@$timestamp");
    $date_obj->setTimezone(new DateTimeZone('Europe/Paris'));
    
    switch ($format) {
        case 'full':
            return ucfirst($jours[$date_obj->format('w')]) . ' ' . 
                   $date_obj->format('d') . ' ' . 
                   $mois[$date_obj->format('n') - 1] . ' ' . 
                   $date_obj->format('Y');
        case 'long':
            return $date_obj->format('d') . ' ' . 
                   $mois[$date_obj->format('n') - 1] . ' ' . 
                   $date_obj->format('Y');
        case 'medium':
            return $date_obj->format('d') . ' ' . 
                   substr($mois[$date_obj->format('n') - 1], 0, 3) . ' ' . 
                   $date_obj->format('Y');
        case 'short':
            return $date_obj->format('d/m/Y');
        case 'time':
            return $date_obj->format('H:i');
        case 'datetime':
            return $date_obj->format('d/m/Y H:i');
        default:
            return $date_obj->format($format);
    }
}

/**
 * Échapper les caractères spéciaux pour l'affichage HTML
 */
/*function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}*/

/**
 * Formater un nombre avec séparateurs français
 */
function format_number($number, $decimals = 0) {
    return number_format($number, $decimals, ',', ' ');
}
?>