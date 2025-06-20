document.addEventListener('DOMContentLoaded', function() {
    // Function to update streak info in the UI
    function updateStreakInfo(streakInfo) {
        // Update streak count
        document.querySelector('.streak-count').textContent = streakInfo.current_streak;
        
        // Update tasks completed
        document.querySelector('.tasks-completed').textContent = streakInfo.tasks_completed_today;
        
        // Update total points
        document.querySelector('.total-points').textContent = streakInfo.total_points;
        
        // Update milestone progress if exists
        if (streakInfo.next_milestone) {
            const progressBar = document.querySelector('.progress');
            const progressText = document.querySelector('.progress-text');
            const milestoneReward = document.querySelector('.milestone-reward');
            
            const progressPercent = Math.min(100, (streakInfo.current_streak / streakInfo.next_milestone.required_streak) * 100);
            progressBar.style.width = progressPercent + '%';
            progressText.textContent = `Next Milestone: ${streakInfo.next_milestone.required_streak} Days`;
            milestoneReward.textContent = `Reward: ${streakInfo.next_milestone.reward_points} Points`;
        }
    }
    
    // Function to handle task completion
    function completeTask(taskId, button) {
        // Disable the button to prevent double submission
        button.disabled = true;
        
        // Create form data
        const formData = new FormData();
        formData.append('action', 'complete_task');
        formData.append('task_id', taskId);
        
        // Send AJAX request
        fetch('streak_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                updateStreakInfo(data.streak_info);
                
                // Update task card
                const taskCard = button.closest('.task-card');
                const pointsElement = taskCard.querySelector('.task-points');
                pointsElement.classList.add('task-complete');
                button.remove();
                
                // Show success message
                const alertContainer = document.createElement('div');
                alertContainer.className = 'alert alert-success';
                alertContainer.textContent = data.message;
                document.querySelector('.tasks-container').insertBefore(
                    alertContainer,
                    document.querySelector('.streak-summary')
                );
                
                // Remove alert after 3 seconds
                setTimeout(() => {
                    alertContainer.remove();
                }, 3000);
            } else {
                throw new Error(data.error || 'Failed to complete task');
            }
        })
        .catch(error => {
            // Re-enable button
            button.disabled = false;
            
            // Show error message
            const alertContainer = document.createElement('div');
            alertContainer.className = 'alert alert-error';
            alertContainer.textContent = error.message;
            document.querySelector('.tasks-container').insertBefore(
                alertContainer,
                document.querySelector('.streak-summary')
            );
            
            // Remove alert after 3 seconds
            setTimeout(() => {
                alertContainer.remove();
            }, 3000);
        });
    }
    
    // Add click event listeners to all complete buttons
    document.querySelectorAll('.complete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.closest('form').querySelector('input[name="task_id"]').value;
            completeTask(taskId, this);
        });
    });
    
    // Function to refresh streak info periodically
    function refreshStreakInfo() {
        fetch('streak_actions.php', {
            method: 'POST',
            body: new URLSearchParams({
                'action': 'get_streak_info'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStreakInfo(data.streak_info);
            }
        })
        .catch(error => {
            console.error('Failed to refresh streak info:', error);
        });
    }
    
    // Refresh streak info every 5 minutes
    setInterval(refreshStreakInfo, 5 * 60 * 1000);
}); 