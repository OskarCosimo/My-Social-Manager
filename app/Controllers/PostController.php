<?php
// app/Controllers/PostController.php

require_once __DIR__ . '/../Models/Post.php';

class PostController {

    /**
     * Handle the form submission from the dashboard
     */
    public function save(): void {
        // Ensure user is authenticated
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        // Retrieve data from POST request (from the hidden input populated by My-WYSIWYG)
        $content = trim($_POST['post_content'] ?? '');
        
        // Retrieve optional scheduling date
        $scheduledAt = $_POST['scheduled_at'] ?? null;
        if (empty($scheduledAt)) {
            $scheduledAt = null;
        }

        // Mock array for media URLs (this will come from your File Manager later)
        // e.g. $mediaUrls = json_decode($_POST['media_urls_hidden'], true);
        $mediaUrls = []; 

        if (empty($content)) {
            // In a real scenario, you'd set a session error and redirect back
            $_SESSION['error'] = "Post content cannot be empty.";
            header('Location: /dashboard');
            exit;
        }

        // Save to database using the Model
        try {
            $postId = Post::create(
                (int)$_SESSION['user_id'], 
                $content, 
                $mediaUrls, 
                $scheduledAt
            );
            
            // Redirect with success message
            $_SESSION['success'] = "Post saved successfully! (ID: " . $postId . ")";
            header('Location: /dashboard');
            exit;
            
        } catch (\Exception $e) {
            // Log error and redirect with failure message
            error_log("Failed to save post: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while saving the post.";
            header('Location: /dashboard');
            exit;
        }
    }
}