<?php
$extra_body_scripts = $extra_body_scripts ?? [];
$inline_scripts = $inline_scripts ?? '';
?>
            </div>
        </div>
    </div>
</main>
<?php foreach ($extra_body_scripts as $script): ?>
    <script src="<?php echo htmlspecialchars($script, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>
<?php if ($inline_scripts !== ''): ?>
    <?php echo $inline_scripts; ?>
<?php endif; ?>
</body>
</html>
