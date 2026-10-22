<?php
// app/Controllers/GroupController.php

require_once __DIR__ . '/../Models/Group.php';
require_once __DIR__ . '/../Models/SocialAccount.php';

class GroupController {
    private $db;
    private $groupModel;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Security check: only logged users can access groups
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }

        // Get DB connection instance using system Singleton
        $this->db = Database::getInstance();
        $this->groupModel = new \App\Models\Group($this->db);
    }

    /**
     * Display the Groups page (list of groups and creation form)
     */
    public function index(): void {
        $userId = (int)$_SESSION['user_id'];
        
        // Fetch user's groups
        $groups = $this->groupModel->getUserGroups($userId);
        
        // Fetch user's connected social accounts
        $accounts = SocialAccount::getUserAccounts($userId); 

        require __DIR__ . '/../Views/groups/index.php';
    }

    /**
     * Save a new group or update an existing one
     */
    public function save(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)$_SESSION['user_id'];
            $groupId = $_POST['group_id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $accountIds = $_POST['accounts'] ?? []; // Array of checked account IDs

            if (empty($name)) {
                $_SESSION['error'] = "Group name cannot be empty.";
                header("Location: /groups");
                exit;
            }

            if ($groupId) {
                // Update existing group
                $this->groupModel->updateGroup($groupId, $userId, $name, $accountIds);
                $_SESSION['success'] = "Group updated successfully!";
            } else {
                // Create new group
                $this->groupModel->createGroup($userId, $name, $accountIds);
                $_SESSION['success'] = "Group created successfully!";
            }
        }
        
        header("Location: /groups");
        exit;
    }

    /**
     * Delete a group
     */
    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
            $userId = (int)$_SESSION['user_id'];
            $groupId = (int)$_POST['group_id'];
            
            $this->groupModel->deleteGroup($groupId, $userId);
            $_SESSION['success'] = "Group deleted successfully!";
        }
        
        header("Location: /groups");
        exit;
    }
}