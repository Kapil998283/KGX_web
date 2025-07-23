<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: /KGX/pages/login.php");
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's current profile image
$profile_image = $user['profile_image'] ?? '/assets/images/team-member-8.png';

// Get user's games
$sql = "SELECT game_name, is_primary FROM user_games WHERE user_id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$main_game = '';
foreach ($user_games as $game) {
    if ($game['is_primary'] == 1) {
        $main_game = $game['game_name'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Settings Page</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/root.css">
  <link rel="stylesheet" href="../../assets/css/dashboard/settings.css">
</head>
<body>
  <div class="settings-container">
    <a href="dashboard.php" class="back-arrow">← <span>Back to Dashboard</span></a>
    <div class="settings-title">User Settings</div>
    
    <!-- Profile Section -->
    <div class="profile-section">
      <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" id="profileImage" class="profile-pic" />
      <br />
      <button class="btn upload-btn" onclick="openImageModal()">Change Image</button>
      <button class="btn remove-btn" onclick="removeProfileImage()">Remove Image</button>
    </div>

    <!-- Username -->
    <div class="form-group">
      <label for="username">Change Username</label>
      <input type="text" id="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($user['username']); ?>">
    </div>

    <!-- Main Game -->
    <div class="form-group">
      <label for="main_game">Main Game</label>
      <select id="main_game" name="main_game">
        <?php
        $games = [
            'PUBG' => 'PUBG',
            'BGMI' => 'BGMI',
            'FREE FIRE' => 'FREE FIRE',
            'COD' => 'COD'
        ];

        // Get user's current main game
        $sql = "SELECT game_name, is_primary FROM user_games WHERE user_id = :user_id AND is_primary = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $main_game = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_main_game = $main_game ? $main_game['game_name'] : '';

        // Show all available games
        foreach ($games as $game_key => $display_name) {
            echo '<option value="' . htmlspecialchars($game_key) . '"' . 
                 ($game_key === $current_main_game ? ' selected' : '') . '>' . 
                 htmlspecialchars($display_name) . '</option>';
        }
        ?>
      </select>
      <small class="form-text">This is the game that will be shown as your main game profile</small>
    </div>

    <!-- Language Selection -->
    <div class="form-group">
      <label for="language">Display Language</label>
      <select id="language">
        <option value="en">English</option>
        <option value="hi">Hindi</option>
        <option value="ar">Arabic</option>
        <option value="ur">Urdu</option>
      </select>
    </div>

    <!-- Save Button -->
    <button class="save-changes-btn" onclick="saveChanges()">Save Changes</button>
  </div>

  <!-- Profile Image Selection Modal -->
  <div id="imageModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" onclick="closeImageModal()">&times;</span>
      <h2 class="modal-title">Select Profile Image</h2>
      <div id="profileImagesGrid" class="profile-images-grid">
        <!-- Profile images will be loaded here -->
      </div>
    </div>
  </div>

  <script>
    const profileImage = document.getElementById('profileImage');
    const imageModal = document.getElementById('imageModal');
    const profileImagesGrid = document.getElementById('profileImagesGrid');
    let selectedImageId = null;
    let selectedImagePath = null;

    function openImageModal() {
      imageModal.style.display = 'block';
      loadProfileImages();
    }

    function closeImageModal() {
      imageModal.style.display = 'none';
      selectedImageId = null;
    }

    function loadProfileImages() {
      fetch('get_profile_images.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            profileImagesGrid.innerHTML = '';
            data.images.forEach(image => {
              const imgContainer = document.createElement('div');
              const imgElement = document.createElement('img');
              imgElement.src = image.path;
              imgElement.alt = 'Profile Option';
              imgElement.style.width = '100%';
              imgElement.style.height = '100%';
              imgElement.style.objectFit = 'cover';
              imgElement.style.borderRadius = '50%';
              
              imgContainer.className = 'profile-image-item';
              imgContainer.appendChild(imgElement);
              imgContainer.onclick = () => selectProfileImage(image.id, image.path);
              profileImagesGrid.appendChild(imgContainer);
            });
          } else {
            alert('Error loading profile images');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error loading profile images');
        });
    }

    function selectProfileImage(imageId, imagePath) {
      selectedImageId = imageId;
      selectedImagePath = imagePath;
      // Remove selected class from all images
      document.querySelectorAll('.profile-image-item').forEach(item => {
        item.classList.remove('selected');
      });
      // Add selected class to clicked image
      event.currentTarget.classList.add('selected');
      
      // Update profile image preview immediately
      profileImage.src = imagePath;
      
      // Save the selection (this will update the database)
      saveProfileImage(imageId, imagePath);
    }

    function saveProfileImage(imageId, imagePath) {
      fetch('update_profile_image.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'image_path=' + encodeURIComponent(imagePath)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update the profile image preview
          profileImage.src = imagePath;
          // Close the modal
          closeImageModal();
          // Show success message
          alert('Profile image updated successfully!');
        } else {
          // Show error message
          alert('Error: ' + data.message);
          // Revert the profile image preview
          profileImage.src = '<?php echo htmlspecialchars($profile_image); ?>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error updating profile image. Please try again.');
        // Revert the profile image preview
        profileImage.src = '<?php echo htmlspecialchars($profile_image); ?>';
      });
    }

    function removeProfileImage() {
      profileImage.src = "https://via.placeholder.com/100";
    }

    function confirmDelete() {
      if (confirm("Are you sure you want to delete your account? This action cannot be undone.")) {
        alert("Account deletion requested (not yet implemented).");
      }
    }

    function saveChanges() {
      const username = document.getElementById("username").value;
      const language = document.getElementById("language").value;
      const mainGame = document.getElementById("main_game").value;

      // Send the updates
      Promise.all([
        // Username update
        fetch('update_username.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'username=' + encodeURIComponent(username)
        }),
        // Main game update
        fetch('update_main_game.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'game_name=' + encodeURIComponent(mainGame)
        })
      ])
      .then(responses => Promise.all(responses.map(r => r.json())))
      .then(results => {
        const errors = results.filter(r => !r.success);
        if (errors.length === 0) {
          alert('Settings updated successfully!');
          // Reload the page to reflect changes
          location.reload();
        } else {
          // Show detailed error messages
          const errorMessages = errors.map(e => e.message + (e.error_details ? '\n\nDetails: ' + e.error_details : '')).join('\n\n');
          alert('Some updates failed:\n\n' + errorMessages);
          console.error('Update errors:', errors);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error updating settings. Please try again.\n\nDetails: ' + error.message);
      });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target == imageModal) {
        closeImageModal();
      }
    }
  </script>
</body>
</html>
