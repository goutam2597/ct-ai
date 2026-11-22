<?php

declare(strict_types=1);

namespace App\PhpStan;

use Illuminate\Database\Eloquent\Builder;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Laravel 13 removed the @method forwarding annotations from
 * Illuminate\Database\Eloquent\Builder, so forwarded query-builder calls
 * (limit, orderBy, ...) fall back to the base builder and lose the model
 * generic. At runtime Eloquent\Builder::__call() returns $this when the base
 * builder returns itself — exactly what this extension tells PHPStan.
 *
 * Registered in phpstan-ext/eloquent-builder-extension.neon.
 */
final class EloquentBuilderForwardedMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /** Forwarded methods used by this codebase that return the builder itself. */
    private const FORWARDED = [
        'limit', 'take', 'offset', 'skip',
        'orderBy', 'inRandomOrder',
        'groupBy', 'having',
    ];

    public function getClass(): string
    {
        return Builder::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), self::FORWARDED, true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        // @return $this semantics: rebuild the same builder generic over its
        // model. The declaring builder itself carries the TModel template.
        $calledUpon = $scope->getType($methodCall->var);

        if ($calledUpon instanceof ObjectType && $calledUpon->getClassName() === $this->getClass()) {
            return $calledUpon;
        }

        if ($calledUpon instanceof GenericObjectType) {
            return $calledUpon;
        }

        $returnTypes = ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $methodCall->getArgs(),
            $methodReflection->getVariants(),
        )->getReturnType();

        return $returnTypes instanceof ObjectType ? $returnTypes : null;
    }
}
