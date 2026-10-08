<?php

/**
 * Moves Code | Charge
 *
 * Prepara avisos de cobrança por e-mail sem executar operações financeiras.
 *
 * @author Djalma Martins
 * @package Moves\Support
 */

namespace Moves\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

class Charge
{
    private string $senderName;

    public function __construct(
        private Email $email,
        private string $senderEmail,
        ?string $senderName = null
    ) {
        $this->senderName = $senderName ?? (defined('APP_NAME') ? APP_NAME : 'Moves');
    }

    /**
     * Prepara e envia um aviso de cobrança, sem criar ou processar pagamentos.
     *
     * @return array{success: bool, message: string}
     */
    public function sendCharge(
        string $recipientEmail,
        string $recipientName,
        int|float|string $amount,
        string $description,
        DateTimeInterface|string $dueDate,
        ?string $paymentUrl = null
    ): array {
        $description = trim($description);
        if (!is_numeric($amount) || !is_finite((float) $amount) || (float) $amount <= 0
            || $description === '' || strlen($description) > 8000
        ) {
            return ['success' => false, 'message' => 'Dados da cobrança inválidos.'];
        }

        try {
            $date = $dueDate instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($dueDate)
                : new DateTimeImmutable($dueDate);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Dados da cobrança inválidos.'];
        }

        if ($paymentUrl !== null && !$this->isSafePaymentUrl($paymentUrl)) {
            return ['success' => false, 'message' => 'Dados da cobrança inválidos.'];
        }

        $formattedAmount = number_format((float) $amount, 2, ',', '.');
        $formattedDueDate = $date->format('d/m/Y');
        $escape = static fn (string $value): string => htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $name = trim($recipientName);
        $greeting = $name === '' ? 'Olá,' : 'Olá, ' . $escape($name) . '.';
        $paymentSection = $paymentUrl === null
            ? ''
            : '<p><a href="' . $escape($paymentUrl) . '">Acessar pagamento ou boleto</a></p>';
        $plainPayment = $paymentUrl === null ? '' : "\nPagamento: {$paymentUrl}";
        $html = '<!doctype html><html lang="pt-BR"><body>'
            . '<p>' . $greeting . '</p>'
            . '<p>Segue o aviso da cobrança:</p>'
            . '<p><strong>Descrição:</strong> ' . $escape($description) . '<br>'
            . '<strong>Valor:</strong> R$ ' . $escape($formattedAmount) . '<br>'
            . '<strong>Vencimento:</strong> ' . $escape($formattedDueDate) . '</p>'
            . $paymentSection
            . '</body></html>';
        $text = $greeting . "\n\nCobrança: {$description}\n"
            . "Valor: R$ {$formattedAmount}\nVencimento: {$formattedDueDate}"
            . $plainPayment;

        return $this->email->sendEmail(
            $recipientEmail,
            'Informações da cobrança',
            $html,
            $this->senderEmail,
            $text,
            $this->senderName,
            [],
            $recipientName
        );
    }

    private function isSafePaymentUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return $scheme === 'https'
            && isset($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }
}
