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

// Streak conversion functionality
function convertPoints() {
    document.getElementById('conversion-modal').style.display = 'flex';
    updatePointsNeeded();
}

function closeModal() {
    document.getElementById('conversion-modal').style.display = 'none';
}

function updatePointsNeeded() {
    const coinsInput = document.getElementById('coins-to-convert');
    const pointsNeeded = document.getElementById('points-needed');
    const maxCoins = parseInt(coinsInput.getAttribute('max'));
    let value = parseInt(coinsInput.value);
    
    // Validate input
    if (isNaN(value) || value < 1) {
        value = 1;
        coinsInput.value = 1;
    } else if (value > maxCoins) {
        value = maxCoins;
        coinsInput.value = maxCoins;
    }
    
    pointsNeeded.textContent = value * 10;
}

function incrementCoins() {
    const input = document.getElementById('coins-to-convert');
    const maxCoins = parseInt(input.getAttribute('max'));
    const currentValue = parseInt(input.value);
    if (currentValue < maxCoins) {
        input.value = currentValue + 1;
        updatePointsNeeded();
    }
}

function decrementCoins() {
    const input = document.getElementById('coins-to-convert');
    const currentValue = parseInt(input.value);
    if (currentValue > 1) {
        input.value = currentValue - 1;
        updatePointsNeeded();
    }
}

function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.left = '50%';
    alertDiv.style.transform = 'translateX(-50%)';
    alertDiv.style.padding = '15px 30px';
    alertDiv.style.borderRadius = '5px';
    alertDiv.style.backgroundColor = type === 'success' ? '#4CAF50' : '#f44336';
    alertDiv.style.color = 'white';
    alertDiv.style.zIndex = '9999';
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

function confirmConversion() {
    const coinsToConvert = document.getElementById('coins-to-convert').value;
    const confirmBtn = document.querySelector('.confirm-btn');
    const cancelBtn = document.querySelector('.cancel-btn');
    
    // Disable buttons during conversion
    confirmBtn.disabled = true;
    cancelBtn.disabled = true;
    confirmBtn.textContent = 'Converting...';
    
    fetch('streak_convert.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            coins: parseInt(coinsToConvert)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(data.message, 'error');
            // Re-enable buttons
            confirmBtn.disabled = false;
            cancelBtn.disabled = false;
            confirmBtn.textContent = 'Convert';
        }
        closeModal();
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error converting points', 'error');
        // Re-enable buttons
        confirmBtn.disabled = false;
        cancelBtn.disabled = false;
        confirmBtn.textContent = 'Convert';
        closeModal();
    });
}

// Add event listeners when document is ready
document.addEventListener('DOMContentLoaded', function() {
    const coinsInput = document.getElementById('coins-to-convert');
    if (coinsInput) {
        coinsInput.addEventListener('input', updatePointsNeeded);
    }
}); 