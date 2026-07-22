<?php

namespace App\Services\Sync\Push;

use App\Enums\MutationType;
use App\Exceptions\Sync\RejectedMutation;
use App\Services\Sync\Push\Handlers\CustomerCreateHandler;
use App\Services\Sync\Push\Handlers\ExpenseCreateHandler;
use App\Services\Sync\Push\Handlers\FavoriteSetHandler;
use App\Services\Sync\Push\Handlers\PosSessionCloseHandler;
use App\Services\Sync\Push\Handlers\PosSessionOpenHandler;
use App\Services\Sync\Push\Handlers\ProductCreateHandler;
use App\Services\Sync\Push\Handlers\SaleCreateHandler;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the {@see MutationHandler} for a mutation type (Design 02 §5.3).
 * Handlers are container-built so they receive their own Action dependencies.
 */
class MutationHandlerRegistry
{
    /** @var array<string, class-string<MutationHandler>> */
    private const MAP = [
        MutationType::CustomerCreate->value => CustomerCreateHandler::class,
        MutationType::ExpenseCreate->value => ExpenseCreateHandler::class,
        MutationType::FavoriteSet->value => FavoriteSetHandler::class,
        MutationType::ProductCreate->value => ProductCreateHandler::class,
        MutationType::PosSessionOpen->value => PosSessionOpenHandler::class,
        MutationType::PosSessionClose->value => PosSessionCloseHandler::class,
        MutationType::SaleCreate->value => SaleCreateHandler::class,
    ];

    public function __construct(private Container $container) {}

    public function for(MutationType $type): MutationHandler
    {
        $handler = self::MAP[$type->value] ?? null;

        if ($handler === null) {
            throw RejectedMutation::validationFailed(__('Unsupported mutation type.'));
        }

        return $this->container->make($handler);
    }
}
