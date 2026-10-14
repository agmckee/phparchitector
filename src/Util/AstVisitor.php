<?php declare(strict_types=1);

namespace PhpArchitector\Util;

use PhpSyntax\Node as SyntaxNode;
use PhpSyntax\Token;
use PhpSyntax\Traverser;
use PhpSyntax\TraverseAction;

/**
 * Base class for AST visitors using the Visitor pattern.
 * 
 * Provides a clean way to traverse PHP AST nodes without manual instanceof checks.
 * Subclasses should override enterNode() to handle specific node types.
 */
abstract class AstVisitor
{
    /**
     * Traverse a node and its children using this visitor.
     *
     * @param SyntaxNode|Token $node The root node to traverse
     * @return static Returns $this for method chaining
     */
    public function traverse(SyntaxNode|Token $node): static
    {
        Traverser::traverse($node, \Closure::fromCallable([$this, 'enter']), \Closure::fromCallable([$this, 'leave']));
        return $this;
    }

    /**
     * Called when entering a node during traversal.
     * Override this method in subclasses to handle specific node types.
     *
     * Return TraverseAction::SkipChildren to skip child nodes,
     * or TraverseAction::Stop to stop traversal entirely.
     *
     * @param SyntaxNode|Token $node The node or token being entered
     * @return TraverseAction|mixed Action to take or null to continue
     */
    abstract protected function enter(SyntaxNode|Token $node): TraverseAction|array|string|int|float|bool|\stdClass|null;

    /**
     * Called when leaving a node during traversal.
     * Override this method in subclasses if cleanup is needed.
     *
     * @param SyntaxNode|Token $node The node or token being left
     * @return void
     */
    protected function leave(SyntaxNode|Token $node): void
    {
        // Default: do nothing on leave
    }

    /**
     * Helper to check if a node is of a specific type.
     *
     * @template T of object
     * @param SyntaxNode $node The node to check
     * @param class-string<T> $className The class name to check
     * @return T|null The node if it matches, null otherwise
     */
    protected function matches(SyntaxNode $node, string $className): ?object
    {
        if ($node instanceof $className) {
            return $node;
        }
        return null;
    }

    /**
     * Helper to collect nodes of a specific type.
     *
     * @template T of object
     * @param array<SyntaxNode> $nodes Array of nodes
     * @param class-string<T> $className The class name to filter by
     * @return array<int, T> Filtered array of matching nodes
     */
    protected function filterNodes(array $nodes, string $className): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if ($node instanceof $className) {
                $result[] = $node;
            }
        }
        return $result;
    }
}
