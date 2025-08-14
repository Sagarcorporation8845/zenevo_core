<?php
/*
--------------------------------------------------------------------------------
-- File: /profile.php (NEW FILE)
-- Description: Employee Self-Service (ESS) page to view and edit profile.
--------------------------------------------------------------------------------
*/

$pageTitle = 'My Profile';
include 'includes/header.php';

// Handle flash messages
$profile_flash = null;
$password_flash = null;
if (isset($_SESSION['flash_message'])) {
    if (isset($_SESSION['flash_message']['form']) && $_SESSION['flash_message']['form'] === 'password') {
        $password_flash = $_SESSION['flash_message'];
    } else {
        $profile_flash = $_SESSION['flash_message'];
    }
    unset($_SESSION['flash_message']);
}

// Fetch current user's full details including profile picture
$user_id = $_SESSION['user_id'];
$sql = "SELECT u.name, u.email, u.profile_picture, e.first_name, e.last_name, e.designation, e.department, e.date_of_joining, e.phone, e.address
        FROM users u
        LEFT JOIN employees e ON u.id = e.user_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

?>

<div class="container mx-auto max-w-4xl">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Left Column: Profile Card -->
        <div class="md:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <div class="relative w-24 h-24 rounded-full mx-auto mb-4">
                    <?php if ($user['profile_picture']): ?>
                        <img src="data:image/jpeg;base64,<?php echo e($user['profile_picture']); ?>" 
                             alt="Profile Picture" class="w-full h-full rounded-full object-cover border-2 border-indigo-200" />
                    <?php else: ?>
                        <div class="w-full h-full rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-3xl">
                            <?php echo e(strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'N', 0, 1))); ?>
                        </div>
                    <?php endif; ?>
                    <!-- Upload button overlay -->
                    <label for="profile-picture-upload" class="absolute bottom-0 right-0 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full p-1 cursor-pointer shadow-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </label>
                    <input type="file" id="profile-picture-upload" class="hidden" accept="image/*" onchange="uploadProfilePicture(this)">
                </div>
                <h2 class="text-xl font-bold text-gray-800"><?php echo e($user['name']); ?></h2>
                <p class="text-sm text-gray-600"><?php echo e($user['designation']); ?></p>
                <div class="mt-4 pt-4 border-t border-gray-200 text-left">
                    <p class="text-sm text-gray-700 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <?php echo e($user['email']); ?>
                    </p>
                    <p class="text-sm text-gray-700 flex items-center mt-2">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Joined on <?php echo date('M d, Y', strtotime(e($user['date_of_joining']))); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Right Column: Edit Forms -->
        <div class="md:col-span-2">
            <!-- Edit Profile Details Form -->
            <div class="bg-white p-8 rounded-lg shadow-md mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Profile Information</h3>
                <?php if ($profile_flash): ?>
                    <div class="mb-4 p-4 rounded-md <?php echo $profile_flash['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <?php echo e($profile_flash['message']); ?>
                    </div>
                <?php endif; ?>
                <form action="actions/user_action.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="space-y-4">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" name="name" id="name" value="<?php echo e($user['name']); ?>" required class="w-full enhanced-input">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address (Read-only)</label>
                            <input type="email" name="email" id="email" value="<?php echo e($user['email']); ?>" readonly class="w-full enhanced-input" disabled>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="bg-white p-8 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Change Password</h3>
                <?php if ($password_flash): ?>
                    <div class="mb-4 p-4 rounded-md <?php echo $password_flash['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <?php echo e($password_flash['message']); ?>
                    </div>
                <?php endif; ?>
                <form action="actions/user_action.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="space-y-4">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" name="current_password" id="current_password" required class="w-full enhanced-input">
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" required class="w-full enhanced-input">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" required class="w-full enhanced-input">
                        </div>
                        <div class="text-right">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function uploadProfilePicture(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file type
    if (!file.type.match('image.*')) {
        alert('Please select a valid image file.');
        return;
    }
    
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB.');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result.split(',')[1]; // Remove data:image/xxx;base64, prefix
        
        // Upload via AJAX
        fetch('actions/profile_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_profile_picture&profile_picture=${encodeURIComponent(base64)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the image immediately
                const profileImg = document.querySelector('img[alt="Profile Picture"]');
                const avatarDiv = document.querySelector('.bg-indigo-100');
                
                if (profileImg) {
                    profileImg.src = e.target.result;
                } else if (avatarDiv) {
                    // Replace avatar with image
                    avatarDiv.outerHTML = `<img src="${e.target.result}" alt="Profile Picture" class="w-full h-full rounded-full object-cover border-2 border-indigo-200" />`;
                }
                
                alert('Profile picture updated successfully!');
            } else {
                alert('Error: ' + (data.error || 'Failed to update profile picture'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while uploading the profile picture.');
        });
    };
    
    reader.readAsDataURL(file);
}
</script>

<?php include 'includes/footer.php'; ?>