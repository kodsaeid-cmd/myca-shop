<?php
declare(strict_types=1);
// Integration boundary: no real payment or secret handling until the provider is selected.
interface MycaPaymentGateway {
    /** Return a redirect URL/transaction identifier, never a browser-supplied amount. */
    public function initiate(int $orderId,int $amountIrr,string $callbackUrl): array;
    /** Must verify provider signature/status/amount/idempotency server-side. */
    public function verify(array $providerMessage): array;
    /** Must be a separate authorized operation with audit log. */
    public function refund(int $orderId,int $amountIrr): array;
}
final class MycaDisabledGateway implements MycaPaymentGateway {
    public function initiate(int $orderId,int $amountIrr,string $callbackUrl): array { throw new LogicException('MYCA real payments are not configured.'); }
    public function verify(array $providerMessage): array { throw new LogicException('MYCA real payments are not configured.'); }
    public function refund(int $orderId,int $amountIrr): array { throw new LogicException('MYCA real refunds are not configured.'); }
}
