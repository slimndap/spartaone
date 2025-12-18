<?php

/**
 * Return true when the given athlete ID (or current session athlete) is an admin.
 *
 * @param string|int|null $athleteId Athlete ID to check; falls back to session athlete when omitted.
 */
function user_is_admin($athleteId = null): bool
{
    $adminIds = ['24379054']; // Extendable list of admin athlete IDs.

    $idToCheck = $athleteId;
    if ($idToCheck === null && isset($_SESSION['strava_tokens']['athlete']['id'])) {
        $idToCheck = $_SESSION['strava_tokens']['athlete']['id'];
    }

    if ($idToCheck === null) {
        return false;
    }

    return in_array((string)$idToCheck, $adminIds, true);
}
