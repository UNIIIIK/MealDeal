<?php
/**
 * update_leaderboard_aggregates.php
 *
 * Rebuilds lightweight aggregate fields on user documents so that
 * leaderboard.php can render real numbers instead of zeros.
 *
 * - For providers (role = food_provider):
 *   - total_listings  : count of listings for that provider
 *   - food_saved      : estimated kg of food saved based on quantity/weight
 *
 * - For consumers (role = food_consumer):
 *   - total_orders    : number of completed carts
 *   - total_spent     : sum of total_price / total_amount (best-effort)
 *   - total_savings   : sum of total_savings in completed carts
 *
 * Safe to run repeatedly; it overwrites aggregates on each run.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/data_functions.php'; // for estimateFoodWeight

use Google\Cloud\Core\Timestamp;

// Allow some time for Firestore queries
set_time_limit(120);

/** @var \Google\Cloud\Firestore\FirestoreClient|\MealDeal\Admin\Firestore\RestFirestoreClient $db */
global $db;

echo "Rebuilding leaderboard aggregates...\n";

/**
 * Helper to get a numeric field from an array with a default.
 */
function md_num($array, $key, $default = 0.0) {
    if (!is_array($array) || !isset($array[$key])) {
        return $default;
    }
    $v = $array[$key];
    if ($v instanceof Timestamp) {
        return $default;
    }
    return is_numeric($v) ? (float) $v : $default;
}

/**
 * Portable helper to update a user document for both gRPC and REST clients.
 */
function md_update_user(string $userId, array $data): void
{
    global $db;

    if (empty($data)) {
        return;
    }

    // REST adapter
    if ($db instanceof \MealDeal\Admin\Firestore\RestFirestoreClient) {
        $db->updateDocument('users', $userId, $data);
        return;
    }

    // Native Firestore PHP client (gRPC)
    if ($db instanceof \Google\Cloud\Firestore\FirestoreClient) {
        $db->collection('users')->document($userId)->set($data, ['merge' => true]);
        return;
    }

    // Fallback: try dynamic method if available
    if (method_exists($db, 'updateDocument')) {
        $db->updateDocument('users', $userId, $data);
    }
}

try {
    /* -----------------------------
     * Providers: listings + food_saved
     * ----------------------------- */
    $usersRef = $db->collection('users');
    $providerDocs = $usersRef
        ->where('role', '=', 'food_provider')
        ->limit(300) // hard safety cap
        ->documents();

    $providerCount = 0;

    foreach ($providerDocs as $user) {
        $providerCount++;
        $userId   = $user->id();
        $userData = $user->data();

        // Query this provider's listings
        $listingsRef = $db->collection('listings');
        $listings    = $listingsRef
            ->where('provider_id', '=', $userId)
            ->limit(200)
            ->documents();

        $totalListings = 0;
        $foodSavedKg   = 0.0;

        foreach ($listings as $listing) {
            $listingData = $listing->data();
            $totalListings++;

            // Reuse estimateFoodWeight from data_functions.php
            $quantity = isset($listingData['quantity']) ? (int) $listingData['quantity'] : 0;
            if ($quantity > 0) {
                if (isset($listingData['weight_per_unit'])) {
                    $weightPerUnit = (float) $listingData['weight_per_unit'];
                } else {
                    $weightPerUnit = estimateFoodWeight($listingData, $quantity);
                }
                $foodSavedKg += $quantity * $weightPerUnit;
            }
        }

        // Patch aggregate fields back onto user document
        md_update_user($userId, [
            'total_listings'  => $totalListings,
            'food_saved'      => round($foodSavedKg, 1),
        ]);

        echo "Provider {$userId}: listings={$totalListings}, food_saved=" . round($foodSavedKg, 1) . " kg\n";
    }

    /* -----------------------------
     * Consumers: orders + savings
     * ----------------------------- */
    $consumerDocs = $usersRef
        ->where('role', '=', 'food_consumer')
        ->limit(300)
        ->documents();

    $consumerCount = 0;

    $cartsRef = $db->collection('cart');

    foreach ($consumerDocs as $user) {
        $consumerCount++;
        $userId   = $user->id();
        $userData = $user->data();

        // Completed orders for this user
        $carts = $cartsRef
            ->where('user_id', '=', $userId)
            ->where('status', '=', 'completed')
            ->limit(300)
            ->documents();

        $totalOrders  = 0;
        $totalSavings = 0.0;
        $totalSpent   = 0.0;

        foreach ($carts as $cart) {
            $cartData = $cart->data();
            $totalOrders++;
            $totalSavings += md_num($cartData, 'total_savings', 0.0);

            // Try a few common field names for amount/price
            $totalSpent += md_num($cartData, 'total_price', 0.0);
            $totalSpent += md_num($cartData, 'total_amount', 0.0);
            $totalSpent += md_num($cartData, 'grand_total', 0.0);
        }

        // Avoid double-counting if multiple keys existed; clamp to sensible range
        if ($totalOrders > 0) {
            $avg = $totalSpent / $totalOrders;
            // If the value looks unreasonably high, keep only first key we tried
            $totalSpent = max(0.0, $avg * $totalOrders);
        }

        md_update_user($userId, [
            'total_orders'   => $totalOrders,
            'total_savings'  => round($totalSavings, 2),
            'total_spent'    => round($totalSpent, 2),
        ]);

        echo "Consumer {$userId}: orders={$totalOrders}, savings=" . round($totalSavings, 2) . ", spent=" . round($totalSpent, 2) . "\n";
    }

    echo "Done. Updated {$providerCount} providers and {$consumerCount} consumers.\n";
} catch (Exception $e) {
    echo "Error updating leaderboard aggregates: " . $e->getMessage() . "\n";
}


