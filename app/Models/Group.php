<?php
// app/Models/Group.php

namespace App\Models;

use PDO;
use Exception;

class Group {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get all groups for a specific user, including their associated account IDs
     */
    public function getUserGroups($userId) {
        $stmt = $this->db->prepare("SELECT * FROM `groups` WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch associated accounts for each group
        foreach ($groups as &$group) {
            $group['account_ids'] = $this->getGroupAccounts($group['id']);
        }

        return $groups;
    }

    /**
     * Get an array of account IDs associated with a specific group
     */
    public function getGroupAccounts($groupId) {
        $stmt = $this->db->prepare("SELECT account_id FROM group_account WHERE group_id = :group_id");
        $stmt->execute(['group_id' => $groupId]);
        
        // Returns a simple array of IDs, e.g., [1, 4, 7]
        return $stmt->fetchAll(PDO::FETCH_COLUMN); 
    }

    /**
     * Create a new group and link the selected social accounts
     */
    public function createGroup($userId, $name, $accountIds = []) {
        try {
            // Start transaction to ensure both group and accounts are saved correctly
            $this->db->beginTransaction();

            // 1. Insert the group
            $stmt = $this->db->prepare("INSERT INTO `groups` (user_id, name) VALUES (:user_id, :name)");
            $stmt->execute([
                'user_id' => $userId,
                'name' => $name
            ]);
            $groupId = $this->db->lastInsertId();

            // 2. Link the accounts to the newly created group
            if (!empty($accountIds)) {
                $this->linkAccountsToGroup($groupId, $accountIds);
            }

            $this->db->commit();
            return $groupId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating group: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing group's name and its associated accounts
     */
    public function updateGroup($groupId, $userId, $name, $accountIds = []) {
        try {
            $this->db->beginTransaction();

            // 1. Update the group name (ensuring it belongs to the user)
            $stmt = $this->db->prepare("UPDATE `groups` SET name = :name WHERE id = :id AND user_id = :user_id");
            $stmt->execute([
                'name' => $name,
                'id' => $groupId,
                'user_id' => $userId
            ]);

            // 2. Delete old account associations
            $stmtDel = $this->db->prepare("DELETE FROM group_account WHERE group_id = :group_id");
            $stmtDel->execute(['group_id' => $groupId]);

            // 3. Insert the new account associations
            if (!empty($accountIds)) {
                $this->linkAccountsToGroup($groupId, $accountIds);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating group: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a group (Associations in group_account are deleted automatically by ON DELETE CASCADE)
     */
    public function deleteGroup($groupId, $userId) {
        $stmt = $this->db->prepare("DELETE FROM `groups` WHERE id = :id AND user_id = :user_id");
        return $stmt->execute([
            'id' => $groupId,
            'user_id' => $userId
        ]);
    }

    /**
     * Helper function to batch insert account links
     */
    private function linkAccountsToGroup($groupId, $accountIds) {
        $sql = "INSERT INTO group_account (group_id, account_id) VALUES ";
        $insertValues = [];
        $params = [];
        
        foreach ($accountIds as $index => $accountId) {
            $insertValues[] = "(:group_id_{$index}, :account_id_{$index})";
            $params["group_id_{$index}"] = $groupId;
            $params["account_id_{$index}"] = $accountId;
        }
        
        $sql .= implode(', ', $insertValues);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}