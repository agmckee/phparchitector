<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Types of patterns that can be detected.
 */
enum PatternType: string
{
    case GodClass = 'god_class';
    case TightCoupling = 'tight_coupling';
    case StaticOveruse = 'static_overuse';
    case LayerViolation = 'layer_violation';
    case Singleton = 'singleton';
    case DependencyInjection = 'dependency_injection';
    case Repository = 'repository';
    case ServiceLocator = 'service_locator';
}
