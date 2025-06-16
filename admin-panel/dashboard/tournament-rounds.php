                                    <span class="badge bg-<?php 
                                        echo match($round['status']) {
                                            'upcoming' => 'primary',
                                            'in_progress' => 'success',
                                            'completed' => 'secondary',
                                            default => 'info'
                                        };
                                    ?>">
                                        <?php echo ucfirst($round['status']); ?>
                                    </span>
                                    <div class="dropdown d-inline-block ms-1">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="changeRoundStatus(<?php echo $round['id']; ?>, 'upcoming')">Upcoming</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="changeRoundStatus(<?php echo $round['id']; ?>, 'in_progress')">In Progress</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="changeRoundStatus(<?php echo $round['id']; ?>, 'completed')">Completed</a></li>
                                        </ul>
                                    </div>
                                </td>
                    <div class="mb-3">
                        <label class="form-label">Special Rules</label>
                        <textarea class="form-control" name="special_rules" id="edit_special_rules" rows="3"></textarea>
                    </div>
                </form>
<script>
function changeRoundStatus(roundId, newStatus) {
    if (!roundId || !newStatus) return;
    
    fetch('update_round_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `round_id=${roundId}&status=${newStatus}&tournament_id=<?php echo $tournament_id; ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating round status: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error updating round status');
        console.error(error);
    });
}

function editRound(round) {
    document.getElementById('edit_id').value = round.id;
    document.getElementById('edit_round_number').value = round.round_number;
    document.getElementById('edit_name').value = round.name;
    document.getElementById('edit_description').value = round.description;
    const time = round.start_time ? new Date(round.start_time).toTimeString().substring(0, 5) : '';
    document.getElementById('edit_start_time').value = time;
    document.getElementById('edit_teams_count').value = round.teams_count;
    document.getElementById('edit_qualifying_teams').value = round.qualifying_teams;
    document.getElementById('edit_map_name').value = round.map_name;
    document.getElementById('edit_kill_points').value = round.kill_points;
    document.getElementById('edit_qualification_points').value = round.qualification_points;
    document.getElementById('edit_special_rules').value = round.special_rules;

    new bootstrap.Modal(document.getElementById('editRoundModal')).show();
}
</script> 