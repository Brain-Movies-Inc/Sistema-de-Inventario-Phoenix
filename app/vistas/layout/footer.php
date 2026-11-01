<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}
?>
    </main>
</div>

<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
