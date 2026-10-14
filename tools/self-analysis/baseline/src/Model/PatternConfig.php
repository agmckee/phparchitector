<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Configuration for pattern detection.
 */
final class PatternConfig
{
    /**
     * @param PatternThresholds $godClass Thresholds for God Class detection
     * @param PatternThresholds $tightCoupling Thresholds for Tight Coupling detection
     * @param PatternThresholds $staticOveruse Thresholds for Static Overuse detection
     * @param LayerConfig $layerViolation Configuration for layer violation detection
     * @param bool $detectSingletons Whether to detect Singleton pattern
     * @param bool $detectDesignPatterns Whether to detect design patterns
     */
    public function __construct(
        public readonly PatternThresholds $godClass,
        public readonly PatternThresholds $tightCoupling,
        public readonly PatternThresholds $staticOveruse,
        public readonly LayerConfig $layerViolation,
        public readonly bool $detectSingletons = true,
        public readonly bool $detectDesignPatterns = true
    ) {}
    
    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self(
            godClass: new PatternThresholds(),
            tightCoupling: new PatternThresholds(10, 5, 300, PatternSeverity::High),
            staticOveruse: new PatternThresholds(5, 3, 200, PatternSeverity::Medium),
            layerViolation: new LayerConfig(),
            detectSingletons: true,
            detectDesignPatterns: true
        );
    }
    
    /**
     * Convert to array for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'god_class' => $this->godClass->toArray(),
            'tight_coupling' => $this->tightCoupling->toArray(),
            'static_overuse' => $this->staticOveruse->toArray(),
            'layer_violation' => $this->layerViolation->toArray(),
            'detect_singletons' => $this->detectSingletons,
            'detect_design_patterns' => $this->detectDesignPatterns,
        ];
    }
}
