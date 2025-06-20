<?php
require_once 'db.php';

class StreakHandler {
    private $db;
    private $user_id;

    public function __construct($db, $user_id) {
        $this->db = $db;
        $this->user_id = $user_id;
    }

    public function checkAndUpdateStreak() {
        try {
            $this->db->beginTransaction();

            // Get user's current streak info
            $stmt = $this->db->prepare("
                SELECT * FROM user_streaks 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $streak = $stmt->fetch(PDO::FETCH_ASSOC);

            $today = date('Y-m-d');
            
            if (!$streak) {
                // Create new streak record
                $stmt = $this->db->prepare("
                    INSERT INTO user_streaks (
                        user_id, 
                        current_streak,
                        longest_streak,
                        last_activity_date,
                        total_points
                    ) VALUES (?, 1, 1, ?, 0)
                ");
                $stmt->execute([$this->user_id, $today]);
                return;
            }

            $lastActivity = new DateTime($streak['last_activity_date']);
            $currentDate = new DateTime($today);
            $daysDiff = $currentDate->diff($lastActivity)->days;

            if ($daysDiff > 1) {
                // Streak broken - reset to 1
                $stmt = $this->db->prepare("
                    UPDATE user_streaks 
                    SET current_streak = 1,
                        last_activity_date = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$today, $this->user_id]);
            } 
            else if ($daysDiff == 1) {
                // Increment streak
                $newStreak = $streak['current_streak'] + 1;
                $newLongest = max($newStreak, $streak['longest_streak']);
                
                $stmt = $this->db->prepare("
                    UPDATE user_streaks 
                    SET current_streak = ?,
                        longest_streak = ?,
                        last_activity_date = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$newStreak, $newLongest, $today, $this->user_id]);

                // Check for milestone achievements
                $this->checkMilestones($newStreak);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating streak: " . $e->getMessage());
        }
    }

    public function checkMilestones($currentStreak) {
        try {
            // Get next unclaimed milestone
            $stmt = $this->db->prepare("
                SELECT m.* 
                FROM streak_milestones m
                LEFT JOIN user_streak_milestones um 
                    ON m.id = um.milestone_id 
                    AND um.user_id = ?
                WHERE um.id IS NULL
                    AND m.required_streak <= ?
                ORDER BY m.required_streak ASC
                LIMIT 1
            ");
            $stmt->execute([$this->user_id, $currentStreak]);
            $milestone = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($milestone) {
                // Award milestone
                $stmt = $this->db->prepare("
                    INSERT INTO user_streak_milestones (
                        user_id,
                        milestone_id,
                        achieved_at
                    ) VALUES (?, ?, NOW())
                ");
                $stmt->execute([$this->user_id, $milestone['id']]);

                // Add reward points
                $stmt = $this->db->prepare("
                    UPDATE user_streaks 
                    SET total_points = total_points + ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$milestone['reward_points'], $this->user_id]);

                // Create notification
                $stmt = $this->db->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        message,
                        created_at
                    ) VALUES (
                        ?,
                        'streak_milestone',
                        ?,
                        NOW()
                    )
                ");
                $message = "Congratulations! You've achieved a {$currentStreak} day streak and earned {$milestone['reward_points']} points!";
                $stmt->execute([$this->user_id, $message]);
            }
        } catch (Exception $e) {
            error_log("Error checking milestones: " . $e->getMessage());
        }
    }

    public function completeTask($taskId) {
        try {
            $this->db->beginTransaction();

            // Check if task already completed today
            $stmt = $this->db->prepare("
                SELECT id 
                FROM user_streak_tasks 
                WHERE user_id = ? 
                    AND task_id = ? 
                    AND DATE(completed_at) = CURDATE()
            ");
            $stmt->execute([$this->user_id, $taskId]);
            
            if (!$stmt->fetch()) {
                // Get task points
                $stmt = $this->db->prepare("
                    SELECT points FROM streak_tasks WHERE id = ?
                ");
                $stmt->execute([$taskId]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                // Record task completion
                $stmt = $this->db->prepare("
                    INSERT INTO user_streak_tasks (
                        user_id,
                        task_id,
                        completed_at
                    ) VALUES (?, ?, NOW())
                ");
                $stmt->execute([$this->user_id, $taskId]);

                // Update total points
                $stmt = $this->db->prepare("
                    UPDATE user_streaks 
                    SET total_points = total_points + ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$task['points'], $this->user_id]);

                $this->checkAndUpdateStreak();
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error completing task: " . $e->getMessage());
            return false;
        }
    }

    public function getStreakInfo() {
        try {
            // Get user streak info
            $stmt = $this->db->prepare("
                SELECT * FROM user_streaks WHERE user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $streak = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$streak) {
                return [
                    'current_streak' => 0,
                    'longest_streak' => 0,
                    'total_points' => 0,
                    'tasks_completed_today' => 0,
                    'next_milestone' => null
                ];
            }

            // Get tasks completed today
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM user_streak_tasks 
                WHERE user_id = ? 
                    AND DATE(completed_at) = CURDATE()
            ");
            $stmt->execute([$this->user_id]);
            $tasksToday = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Get next milestone
            $stmt = $this->db->prepare("
                SELECT m.* 
                FROM streak_milestones m
                LEFT JOIN user_streak_milestones um 
                    ON m.id = um.milestone_id 
                    AND um.user_id = ?
                WHERE um.id IS NULL
                ORDER BY m.required_streak ASC
                LIMIT 1
            ");
            $stmt->execute([$this->user_id]);
            $nextMilestone = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'current_streak' => $streak['current_streak'],
                'longest_streak' => $streak['longest_streak'],
                'total_points' => $streak['total_points'],
                'tasks_completed_today' => $tasksToday,
                'next_milestone' => $nextMilestone
            ];
        } catch (Exception $e) {
            error_log("Error getting streak info: " . $e->getMessage());
            return null;
        }
    }
}
?> 