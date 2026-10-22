<?php
// app/Controllers/HomeController.php

require_once __DIR__ . '/../Core/Database.php';

class HomeController {
    /**
     * Render the public landing page
     */
    public function index(): void {
        // If the user is already logged in, we can safely redirect them to the dashboard
        // Or we can just let them see the landing page. Let's pass the session status to the view.
        $isLoggedIn = isset($_SESSION['user_id']);
        
        // Fetch plans from the database to display on the homepage
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM plans ORDER BY price ASC");
        $plans = $stmt->fetchAll();
        
        require_once __DIR__ . '/../Views/home/index.php';
    }
}