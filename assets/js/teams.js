document.addEventListener('DOMContentLoaded', function() {
    // Banner selection
    const bannerOptions = document.querySelectorAll('.banner-option');
    bannerOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            bannerOptions.forEach(opt => opt.classList.remove('selected'));
            // Add selected class to clicked option
            this.classList.add('selected');
            // Check the radio input
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        });
    });

    // Search functionality
    const searchInput = document.getElementById('team-search');
    const searchBtn = document.querySelector('.search-btn');
    let searchTimeout;

    if (searchInput && searchBtn) {
        // Search on input with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 300);
        });

        // Search on button click
        searchBtn.addEventListener('click', function() {
            performSearch(searchInput.value);
        });

        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch(this.value);
            }
        });
    }

    // Form validation and submission
    const createTeamForm = document.getElementById('createTeamForm');
    if (createTeamForm) {
        createTeamForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const name = document.getElementById('name').value.trim();
            const logo = document.getElementById('logo').value.trim();
            const language = document.getElementById('language').value;
            const maxMembers = document.getElementById('max_members').value;
            const description = document.getElementById('description').value.trim();
            const selectedBanner = document.querySelector('input[name="banner_id"]:checked');

            let isValid = true;
            let errorMessage = '';

            // Validation checks
            if (name.length < 3) {
                errorMessage = 'Team name must be at least 3 characters long';
                isValid = false;
            } else if (!logo) {
                errorMessage = 'Please provide a team logo URL';
                isValid = false;
            } else if (!selectedBanner) {
                errorMessage = 'Please select a team banner';
                isValid = false;
            } else if (!language) {
                errorMessage = 'Please select a team language';
                isValid = false;
            } else if (!maxMembers) {
                errorMessage = 'Please select maximum team members';
                isValid = false;
            } else if (!description) {
                errorMessage = 'Please provide a team description';
                isValid = false;
            }

            if (!isValid) {
                showError(errorMessage);
                return;
            }

            // If validation passes, submit the form
            const formData = new FormData(createTeamForm);
            
            // Show loading state
            const submitButton = createTeamForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = 'Creating Team...';

            fetch(createTeamForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || 'Error creating team');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = '/newapp/pages/teams/index.php';
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Error creating team');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError(error.message);
                // Reset button state
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    }

    // Handle join team button clicks
    const joinButtons = document.querySelectorAll('.join-team');
    joinButtons.forEach(button => {
        button.addEventListener('click', function() {
            const teamId = this.getAttribute('data-team-id');
            sendJoinRequest(teamId);
        });
    });
});

function sendJoinRequest(teamId) {
    // Create form data
    const formData = new FormData();
    formData.append('team_id', teamId);

    // Send request
    fetch('/newapp/pages/teams/send_join_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert(data.message);
            // Reload the page to update the button state
            window.location.reload();
        } else {
            // Show error message
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while sending the request');
    });
}

// Search function
async function performSearch(query) {
    const teamCardsContainer = document.querySelector('.team-cards-container');
    if (!teamCardsContainer) return;

    try {
        // Remove existing results count
        const existingCount = document.querySelector('.search-results-count');
        if (existingCount) {
            existingCount.remove();
        }

        // Show loading state
        teamCardsContainer.innerHTML = '<div class="loading pulse">Searching teams...</div>';

        const response = await fetch(`/newapp/pages/teams/search_teams.php?query=${encodeURIComponent(query)}`);
        const data = await response.json();

        if (data.success) {
            // Add results count
            const countText = query ? 
                `Found ${data.teams.length} team${data.teams.length !== 1 ? 's' : ''} for "${query}"` : 
                `Showing all teams`;
            
            const countElement = document.createElement('div');
            countElement.className = 'search-results-count';
            countElement.textContent = countText;
            teamCardsContainer.parentElement.insertBefore(countElement, teamCardsContainer);

            if (data.teams.length === 0) {
                teamCardsContainer.innerHTML = `
                    <div class="no-results">
                        <div style="margin-bottom: 1rem;">No teams found matching "${query}"</div>
                        <div style="font-size: 0.9rem; opacity: 0.7;">Try different keywords or create a new team</div>
                    </div>`;
                return;
            }

            // Build team cards HTML with staggered animation
            const teamsHTML = data.teams.map((team, index) => `
                <div class="team-card" style="animation-delay: ${index * 0.1}s">
                    <img src="${team.logo}" alt="${team.name}" class="team-logo" onerror="this.src='/newapp/assets/images/default-team-logo.png'">
                    <h3>${team.name}</h3>
                    <p>${team.description}</p>
                    <p>Language: ${team.language}</p>
                    <p>Members: ${team.current_members}/${team.max_members}</p>
                    <p>Captain: ${team.captain_name}</p>
                    <button class="rc-btn" onclick="window.location.href='/newapp/pages/teams/view.php?id=${team.id}'">
                        ${team.current_members >= team.max_members ? 'Team Full' : 'Join Team'}
                    </button>
                </div>
            `).join('');

            teamCardsContainer.innerHTML = teamsHTML;

            // Disable join button for full teams
            const fullTeams = teamCardsContainer.querySelectorAll('.team-card');
            fullTeams.forEach(card => {
                const button = card.querySelector('.rc-btn');
                if (button.textContent.trim() === 'Team Full') {
                    button.disabled = true;
                    button.classList.add('disabled');
                }
            });
        } else {
            throw new Error(data.message || 'Error searching teams');
        }
    } catch (error) {
        teamCardsContainer.innerHTML = `
            <div class="error">
                <div style="margin-bottom: 0.5rem;">Error searching teams</div>
                <div style="font-size: 0.9rem; opacity: 0.7;">Please try again later</div>
            </div>`;
    }
}

// Error message display function
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger';
    errorDiv.textContent = message;
    
    // Remove any existing error messages
    const existingError = document.querySelector('.alert-danger');
    if (existingError) {
        existingError.remove();
    }
    
    // Insert error message at the top of the form
    const form = document.getElementById('createTeamForm');
    form.insertBefore(errorDiv, form.firstChild);
    
    // Scroll to error message
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Success message display function
function showSuccess(message) {
    const successDiv = document.createElement('div');
    successDiv.className = 'alert alert-success';
    successDiv.textContent = message;
    
    // Remove any existing messages
    const existingMessages = document.querySelectorAll('.alert');
    existingMessages.forEach(msg => msg.remove());
    
    // Insert success message at the top of the form
    const form = document.getElementById('createTeamForm');
    form.insertBefore(successDiv, form.firstChild);
    
    // Scroll to success message
    successDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Banner selection functionality
function selectBanner(element, bannerId) {
    // Remove selected class from all options
    document.querySelectorAll('.banner-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Add selected class to clicked option
    element.classList.add('selected');
    
    // Check the radio input
    element.querySelector('input[type="radio"]').checked = true;
}

// Edit Team Modal Functions
function openEditModal(teamId) {
    const modal = document.getElementById('editTeamModal');
    const form = document.getElementById('editTeamForm');
    const errorDiv = document.getElementById('editTeamError');
    
    // Clear any previous errors
    errorDiv.style.display = 'none';
    
    // Reset form
    form.reset();
    document.getElementById('editTeamId').value = teamId;
    
    // Fetch team data
    fetch(`/newapp/pages/teams/get_team.php?id=${teamId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fill form with team data
                document.getElementById('editTeamName').value = data.team.name;
                document.getElementById('editTeamLogo').value = data.team.logo;
                document.getElementById('editTeamDescription').value = data.team.description;
                document.getElementById('editTeamLanguage').value = data.team.language;

                // Select the current banner
                const bannerRadio = document.querySelector(`input[name="banner_id"][value="${data.team.banner_id}"]`);
                if (bannerRadio) {
                    bannerRadio.checked = true;
                    bannerRadio.closest('.banner-option').classList.add('selected');
                }

                // Show modal with animation
                modal.style.display = 'flex';
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
            } else {
                showError(data.message || 'Error loading team data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error loading team data');
        });
}

function closeEditModal() {
    const modal = document.getElementById('editTeamModal');
    const errorDiv = document.getElementById('editTeamError');
    
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        errorDiv.style.display = 'none';
    }, 300);
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('editTeamModal');
    if (event.target === modal) {
        closeEditModal();
    }
});

// Handle team edit form submission
document.getElementById('editTeamForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const errorDiv = document.getElementById('editTeamError');
    
    // Validate form data
    if (!formData.get('name') || !formData.get('logo') || !formData.get('description') || 
        !formData.get('language') || !formData.get('banner_id')) {
        errorDiv.textContent = 'All fields are required';
        errorDiv.style.display = 'block';
        return;
    }
    
    // Clear any previous errors
    errorDiv.style.display = 'none';
    
    // Show loading state
    const submitButton = this.querySelector('.save-btn');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = 'Saving Changes...';
    
    fetch('/newapp/pages/teams/update_team.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            closeEditModal();
            location.reload();
        } else {
            errorDiv.textContent = data.message || 'Error updating team';
            errorDiv.style.display = 'block';
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorDiv.textContent = 'An error occurred while updating the team. Please try again.';
        errorDiv.style.display = 'block';
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
});

// Delete team function
function deleteTeam() {
    const teamId = document.getElementById('editTeamId').value;
    
    if (confirm('Are you sure you want to delete this team? This action cannot be undone.')) {
        fetch('/newapp/pages/teams/delete_team.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ team_id: teamId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/newapp/pages/teams/index.php';
            } else {
                showError(data.message || 'Error deleting team');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error deleting team');
        });
    }
}

// Remove team member function
function removeMember(memberId) {
    if (confirm('Are you sure you want to remove this member from the team?')) {
        fetch('/newapp/pages/teams/remove_member.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ member_id: memberId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showError(data.message || 'Error removing team member');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error removing team member');
        });
    }
}
