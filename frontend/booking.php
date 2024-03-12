<?php

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('./vendor/autoload.php');
require_once('/home/mike/it490/path.inc');
require_once('/home/mike/it490/get_host_info.inc');
require_once('/home/mike/it490/rabbitMQLib.inc');

use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = "password";

if (!isset($_COOKIE['userToken'])) {
    header("Location: index.php"); 
    exit;
}

try {
    $decoded = JWT::decode($_COOKIE['userToken'], new Key($key, 'HS256'));
    $username = $decoded->username;
    $email = $decoded->email;
} catch (Exception $e) {
    echo "Error decoding token: " . $e->getMessage();
    exit;
}

$results = []; // Initialize results array

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['term'], $_POST['location'])) {
    // Create a new instance of rabbitMQClient
    $client = new rabbitMQClient("/home/mike/it490/testRabbitMQ.ini", "testServer");
    $request = [
        'type' => "yelpSearch",
        'term' => trim($_POST['term']),
        'location' => trim($_POST['location']),
    ];

    $response = $client->send_request($request);

    if (!empty($response) && isset($response['businesses'])) {
        $results = $response['businesses'];
    } else {
        echo "Failed to retrieve Yelp search results.";
    }
}

// Handle reservation form submission
if (isset($_POST['makeReservation'])) {
    $selectedRestaurantId = $_POST['restaurantId'];
    $reservationDate = $_POST['reservationDate'];
    $reservationTime = $_POST['reservationTime'];
    $guests = $_POST['guests'];
    $specialRequests = isset($_POST['specialRequests']) ? $_POST['specialRequests'] : '';

    $reservationRequest = [
        'type' => "makeReservation",
        'username' => $username,
        'restaurantId' => $selectedRestaurantId,
        'reservationDate' => $reservationDate,
        'reservationTime' => $reservationTime,
        'guests' => $guests,
	'specialRequests' => $specialRequests
    ];

    $client = new rabbitMQClient("/home/mike/it490/testRabbitMQ.ini", "testServer");
    $reservationResponse = $client->send_request($reservationRequest);

    if ($reservationResponse && $reservationResponse['success']) {
        echo "Reservation successful!";
    } else {
        echo "Failed to make a reservation.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Search and Reservation</title>
    <link rel="stylesheet" href="home.css">
</head>
<body>
    <div class="navbar">
        <a href="welcome.php">Home</a>
        <a href="userProfile.php">Profile</a>
        <a href="testing.php">Search</a>
        <a href="radiusSearch.php">Search by Location</a>
        <a href="booking.php">Reservations</a>
        <a href="review.php">Leave a Review</a>
    </div>

    <h1>Book a Reservation</h1>
    <form action="booking.php" method="post">
        <label for="term">Search Term:</label>
        <input type="text" id="term" name="term" required><br>
        <label for="location">Location:</label>
        <input type="text" id="location" name="location" required><br>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($results)): ?>
        <h2>Results:</h2>
        <div id="results">
            <?php foreach ($results as $business): ?>
                <div class="result">
                    <img src="<?php echo htmlspecialchars($business['image_url']); ?>" alt="Restaurant Image" style="width:100px;height:100px;"><br>
                    <strong><?php echo htmlspecialchars($business['name']); ?></strong><br>
                    Rating: <?php echo htmlspecialchars($business['rating']); ?><br>
                    Address: <?php echo htmlspecialchars(implode(", ", $business['location']['display_address'])); ?><br>
                    <form action="booking.php" method="post">
                        <input type="hidden" name="restaurantId" value="<?php echo htmlspecialchars($business['id']); ?>">
                        <label for="reservationDate">Date:</label>
                        <input type="date" id="reservationDate" name="reservationDate" required>
                        <label for="reservationTime">Time:</label>
                        <input type="time" id="reservationTime" name="reservationTime" required>
                        <label for="guests">Guests:</label>
                        <input type="number" id="guests" name="guests" min="1" required>
                        <button type="submit" name="makeReservation">Make Reservation</button>
<label for="specialRequests">Special Requests:</label>
<textarea id="specialRequests" name="specialRequests"></textarea>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="logout.php" method="post">
        <button type="submit">Logout</button>
    </form>

</body>
</html>
