<?php

declare(strict_types=1);

namespace QueryBuilder\Controller\Front;

use QueryBuilder\Service\HookResultPresenter;
use QueryBuilder\Service\RuntimeContextFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;

/**
 * JSON endpoint for the front javascript (React cart, ajax blocks...):
 * executes the active rules bound to a hook code — typically a "virtual"
 * hook declared in the dictionary, not rendered by Smarty — and returns
 * the product ids selected by their display actions.
 *
 * Same payload as the query_builder_products() Twig function (HookResultPresenter).
 */
#[Route('/query_builder', name: 'query_builder_front_')]
class QueryBuilderApiController extends BaseFrontController
{
    #[Route('/products/{hookCode}', name: 'products', requirements: ['hookCode' => '[a-zA-Z0-9_.\-]+'], methods: 'GET')]
    public function products(
        Request $request,
        string $hookCode,
        HookResultPresenter $hookResultPresenter,
        RuntimeContextFactory $runtimeContextFactory,
    ): JsonResponse {
        $runtimeContext = $runtimeContextFactory->fromSession(
            productId: $request->query->getInt('product_id') ?: null
        );

        return new JsonResponse($hookResultPresenter->present($hookCode, $runtimeContext));
    }
}
