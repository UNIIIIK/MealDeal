document.addEventListener('DOMContentLoaded', function() {
    window.refreshLeaderboard = function() { location.reload(); }
    window.viewUserDetails = function(userId) {
        alert('User details for: ' + userId);
    }
    window.sendReward = function(userId) {
        const input = document.getElementById('rewardUserId');
        if (input) input.value = userId;
        const modal = new bootstrap.Modal(document.getElementById('rewardModal'));
        modal.show();
    }
});


