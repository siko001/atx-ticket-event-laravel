<?php

namespace AtxDigital\Ticketing\Filament\Resources\OrderResource\Pages;

use AtxDigital\Ticketing\Contracts\PaymentGatewayContract;
use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Exceptions\RefundFailedException;
use AtxDigital\Ticketing\Filament\Resources\OrderResource;
use AtxDigital\Ticketing\Jobs\GenerateTicketAssets;
use AtxDigital\Ticketing\Jobs\SendOrderConfirmation;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Services\OrderPaymentService;
use AtxDigital\Ticketing\Support\Authorize;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Bus;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function order(): Order
    {
        /** @var Order */
        return $this->getRecord();
    }

    protected function getHeaderActions(): array
    {
        return [
            // Refunds only exist for orders that actually took money.
            Action::make('refund')
                ->label('Refund')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This refunds the full amount via Stripe and marks the order as refunded.')
                ->visible(fn (): bool => $this->order()->status === OrderStatus::Paid
                    && $this->order()->total > 0
                    && Authorize::allows('refund', $this->order()))
                ->action(function (): void {
                    $order = $this->order();

                    try {
                        app(PaymentGatewayContract::class)->refund($order);
                    } catch (RefundFailedException $e) {
                        Notification::make()
                            ->title('Refund failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    app(OrderPaymentService::class)->markRefunded($order);

                    Notification::make()
                        ->title("Order {$order->order_number} refunded")
                        ->success()
                        ->send();
                }),

            // Free (total 0) and still-pending orders are cancelled instead —
            // no gateway involved; the tickets simply stop being valid.
            Action::make('cancelOrder')
                ->label('Cancel order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Cancelling frees the reserved places and invalidates the tickets. No payment is involved.')
                ->visible(function (): bool {
                    $order = $this->order();

                    $cancellable = $order->status === OrderStatus::Pending
                        || ($order->status === OrderStatus::Paid && $order->total === 0);

                    return $cancellable && Authorize::allows('cancel', $order);
                })
                ->action(function (): void {
                    $order = $this->order();

                    app(OrderPaymentService::class)->markCancelled($order);

                    Notification::make()
                        ->title("Order {$order->order_number} cancelled")
                        ->success()
                        ->send();
                }),

            Action::make('resendTickets')
                ->label('Resend tickets')
                ->icon('heroicon-o-envelope')
                ->visible(fn (): bool => $this->order()->status === OrderStatus::Paid)
                ->action(function (): void {
                    $order = $this->order();

                    Bus::chain([
                        new GenerateTicketAssets($order),
                        new SendOrderConfirmation($order),
                    ])->dispatch();

                    Notification::make()
                        ->title('Confirmation email queued')
                        ->success()
                        ->send();
                }),
        ];
    }
}
