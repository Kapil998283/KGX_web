document.addEventListener('DOMContentLoaded', function() {
    // Function to update streak info in the UI
    function updateStreakInfo(streakInfo) {
        document.getElementById('current-streak').textContent = streakInfo.current_streak;
        document.getElementById('streak-points').textContent = streakInfo.streak_points;
        
        // Update progress bar if it exists
        const progressBar = document.querySelector('.progress');
        if (progressBar) {
            const requiredPoints = parseInt(document.querySelector('.progress-text').textContent.split('/')[1]);
            const progress = Math.min(100, (streakInfo.streak_points / requiredPoints) * 100);
            progressBar.style.width = `${progress}%`;
            
            // Update points text
            document.querySelector('.progress-text').textContent = 
                `${streakInfo.streak_points} / ${requiredPoints} points`;
        }
    }
    
    // Function to handle task completion
    function completeTask(taskId) {
        const taskCard = document.querySelector(`[data-task-id="${taskId}"]`);
        const completeButton = taskCard.querySelector('.complete-task-btn');
        
        // Disable button while processing
        completeButton.disabled = true;
        
        fetch('streak_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=complete_task&task_id=${taskId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update task card
                taskCard.classList.add('completed');
                const taskFooter = taskCard.querySelector('.task-footer');
                taskFooter.innerHTML = `
                    <span class="points">${taskCard.querySelector('.points').textContent}</span>
                    <span class="completed-label">Completed</span>
                `;
                
                // Update streak info
                updateStreakInfo(data.streak_info);
                
                // Show success message
                showAlert('Task completed successfully! Keep up the great work!');
            } else {
                throw new Error(data.error || 'Failed to complete task');
            }
        })
        .catch(error => {
            // Re-enable button
            completeButton.disabled = false;
            
            // Show error message
            showAlert(error.message, 'error');
        });
    }
    
    // Add click event listeners to all complete buttons
    document.querySelectorAll('.complete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const taskId = this.closest('form').querySelector('input[name="task_id"]').value;
            completeTask(taskId);
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

function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alert-container');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
} 