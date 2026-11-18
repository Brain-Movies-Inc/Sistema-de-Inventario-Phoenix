<?php
declare(strict_types=1);

/**
 * Tokens de idempotencia del formulario de venta.
 *
 * Cada pestaña del POS recibe un token distinto y el token se consume una sola
 * vez al intentar emitir, para que un doble clic o un reenvío del navegador no
 * emita dos facturas por la misma venta.
 */
final class TokenEmision
{
    /**
     * Máximo de formularios de venta abiertos simultáneamente. Cada pestaña
     * recibe un token distinto y el token se consume al intentar emitir.
     */
    private const MAX_TOKENS = 10;

    /**
     * Vigencia máxima de un token de emisión, en segundos (2 horas).
     */
    private const TTL = 7200;

    /**
     * Crea un token de idempotencia para el formulario de venta.
     *
     * Se guardan varios tokens para permitir varias pestañas abiertas; cada
     * token se consume una sola vez al procesar el POST de emisión.
     */
    public static function generar(): string
    {
        $token  = bin2hex(random_bytes(16));
        $tokens = $_SESSION['pos_tokens'] ?? [];
        $ahora  = time();

        if (!is_array($tokens)) {
            $tokens = [];
        }

        // Se descartan los tokens vencidos antes de agregar el nuevo.
        $tokens = array_filter(
            $tokens,
            static fn ($marca): bool => is_int($marca)
                && ($ahora - $marca) <= self::TTL
        );

        $tokens[$token] = $ahora;

        if (count($tokens) > self::MAX_TOKENS) {
            $tokens = array_slice($tokens, -self::MAX_TOKENS, null, true);
        }

        $_SESSION['pos_tokens'] = $tokens;

        return $token;
    }

    /**
     * Valida y consume el token de idempotencia del POST de emisión.
     *
     * Un token ausente, repetido o de otra sesión se rechaza antes de tocar
     * la base de datos, evitando que un doble clic o un reenvío del navegador
     * emita dos facturas por la misma venta.
     */
    public static function consumir(): void
    {
        $token  = $_POST['token_emision'] ?? '';
        $tokens = $_SESSION['pos_tokens'] ?? [];

        if (!is_string($token)
            || $token === ''
            || !is_array($tokens)
            || !isset($tokens[$token])
        ) {
            throw new InvalidArgumentException(
                'La solicitud de emisión ya fue procesada o expiró. Revisa el carrito e intenta de nuevo.'
            );
        }

        $emitidoEn = $tokens[$token];

        if (!is_int($emitidoEn)
            || $emitidoEn <= 0
            || (time() - $emitidoEn) > self::TTL
        ) {
            unset($tokens[$token]);
            $_SESSION['pos_tokens'] = $tokens;

            throw new InvalidArgumentException(
                'La solicitud de emisión ya fue procesada o expiró. Revisa el carrito e intenta de nuevo.'
            );
        }

        unset($tokens[$token]);
        $_SESSION['pos_tokens'] = $tokens;
    }
}
