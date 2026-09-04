<?php

declare(strict_types=1);

namespace QueryBuilder\Enum;

enum Context: string
{
    case GLOBAL_SCOPE = 'GLOBAL';
    case CATEGORY = 'CATEGORY';
    case BRAND = 'BRAND';
    case PRODUCT = 'PRODUCT';
    case CART = 'CART';
    case ORDER = 'ORDER';
    case CUSTOMER = 'CUSTOMER';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL_SCOPE => 'Global (tous les contextes)',
            self::CATEGORY => 'Catégorie',
            self::BRAND => 'Marque',
            self::PRODUCT => 'Produit',
            self::CART => 'Panier',
            self::ORDER => 'Commande',
            self::CUSTOMER => 'Client',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GLOBAL_SCOPE => 'Règle non rattachée à un objet : peut écouter les hooks de tous les contextes.',
            self::CATEGORY => 'Règle évaluée sur une catégorie du catalogue.',
            self::BRAND => 'Règle évaluée sur une marque.',
            self::PRODUCT => 'Règle évaluée sur un produit.',
            self::CART => 'Règle évaluée sur le panier du client courant.',
            self::ORDER => 'Règle évaluée sur une commande.',
            self::CUSTOMER => 'Règle évaluée sur le client courant (connecté).',
        };
    }
}
