<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Types of dependencies between classes.
 */
enum DependencyType: string
{
    case Extends = 'extends';
    case Implements = 'implements';
    case TypeHint = 'type_hint';
    case ReturnType = 'return_type';
    case PropertyType = 'property_type';
    case Instantiation = 'instantiation';
    case StaticCall = 'static_call';
    case UseStatement = 'use_statement';
}
