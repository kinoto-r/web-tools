<?php if (!empty($debugLogs)): ?>
    <script>
        console.group('Dashboard Debug');
        <?php foreach ($debugLogs as $log): ?>
        console.log(<?php echo json_encode($log, JSON_UNESCAPED_UNICODE); ?>);
        <?php endforeach; ?>
        <?php if ($isError): ?>
        console.error('ダッシュボード処理でエラーが発生しました。');
        <?php endif; ?>
        console.groupEnd();
    </script>
<?php endif; ?>

<script>
    const toggleDetails = document.getElementById('toggle-details');
    if (toggleDetails) {
        toggleDetails.addEventListener('click', (event) => {
            event.preventDefault();
            document.body.classList.toggle('show-detail');
            console.log('詳細管理の表示切り替え:', document.body.classList.contains('show-detail'));
        });
    }
</script>