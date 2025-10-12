<?php
// Test file per debug del tour editing
error_log('=== TEST TOUR EDIT DEBUG ===');
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('POST data: ' . print_r($_POST, true));
error_log('GET data: ' . print_r($_GET, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('Form submitted via POST');
    if (isset($_POST['save_tour'])) {
        error_log('save_tour field present: ' . $_POST['save_tour']);
    }
    if (isset($_POST['post_id'])) {
        error_log('post_id field present: ' . $_POST['post_id']);
    }
    if (isset($_POST['tour_nonce'])) {
        error_log('tour_nonce field present: ' . $_POST['tour_nonce']);
    }
} else {
    error_log('No POST data received');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Tour Edit</title>
</head>
<body>
    <h1>Test Tour Edit</h1>
    <form method="post" action="">
        <input type="hidden" name="save_tour" value="1">
        <input type="hidden" name="post_id" value="205">
        <input type="hidden" name="tour_nonce" value="<?php echo wp_create_nonce('save_tour_nonce'); ?>">
        <input type="text" name="titolo" value="Test Tour" required>
        <button type="submit">Test Submit</button>
    </form>
</body>
</html> 