<?php

namespace Bavragor\FunctionDocumentor\Visitor;

use Bavragor\FunctionDocumentor\Formatter\FormatterInterface;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use Psr\Log\LoggerAwareTrait;

class FunctionParsingVisitor extends NodeVisitorAbstract
{
    use LoggerAwareTrait;

    /**
     * Detected function calls regarding $this->functionCalls
     *
     * ${function} => [
     *     ${argument1} => mixed
     * ]
     *
     * @var array
     */
    public $functions = [];

    /**
     * @var string
     */
    private $filePath;

    /**
     * Classes with their callable functions
     *
     * [
     *     ${class} => [
     *         ${function}
     *     ]
     * ]
     *
     * @var array
     */
    protected $functionCalls;

    /**
     * Formatter[] prioritized by their index
     *
     * @var FormatterInterface[]
     */
    protected $formatters;

    /**
     * FunctionParsingVisitor constructor.
     * @param array $functionCalls
     * @param FormatterInterface[] $formatters
     */
    public function __construct(array $functionCalls, array $formatters)
    {
        $this->functionCalls = $functionCalls;

        $this->formatters = array_filter($formatters, function ($formatter) {
            if ($formatter instanceof FormatterInterface) {
                return true;
            }

            $this->logger->warning('Provided formatter did not implement the interface');

            return false;
        });
    }

    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
    }

    public function leaveNode(Node $node)
    {
        $this->handleFunctionCallOnProperty($node);
        $this->handleStaticFunctionCall($node);
    }

    /**
     * Handles a function call like ${variable}->${property}->${class}->${function}
     *
     * @param Node $node
     */
    protected function handleFunctionCallOnProperty(Node $node)
    {
        if (
            $node instanceof Node\Expr\MethodCall &&
            $node->var instanceof Node\Expr\PropertyFetch &&
            array_key_exists($node->var->name, $this->functionCalls) &&
            in_array($node->name, $this->functionCalls[$node->var->name], true)
        ) {
            $class = $node->var->name;
            $function = $node->name;
            $functionCall = sprintf('%s->%s', $class, $function);

            $arguments = [];

            foreach ($node->args as $index => $arg) {

                if (isset($this->functions[$functionCall]) === false) {
                    $this->functions[$functionCall] = [];
                }

                $argumentValue = $this->handleArgumentValue($arg->value);

                foreach ($this->formatters as $formatter) {
                    $argumentValue = $formatter->formatArgument($class, $function, $argumentValue);
                }

                $arguments[] = $argumentValue;
            }

            $this->functions[$functionCall][] = $arguments;
        }
    }

    /**
     * Handles a function call like like ${class}::${function}
     *
     * @param Node $node
     */
    protected function handleStaticFunctionCall(Node $node)
    {
        if (
            $node instanceof Node\Expr\StaticCall &&
            array_key_exists($node->class->parts[0], $this->functionCalls) &&
            in_array($node->name, $this->functionCalls[$node->class->parts[0]], true)
        ) {
            $class = $node->class->parts[0];
            $function = $node->name;
            $functionCall = sprintf('%s::%s', $class, $function);

            $arguments = [];

            foreach ($node->args as $index => $arg) {

                if (isset($this->functions[$functionCall]) === false) {
                    $this->functions[$functionCall] = [];
                }

                $argumentValue = $this->handleArgumentValue($arg->value);

                foreach ($this->formatters as $formatter) {
                    $argumentValue = $formatter->formatArgument($class, $function, $argumentValue);
                }

                $arguments[] = $argumentValue;
            }

            $this->functions[$functionCall][] = $arguments;
        }
    }

    /**
     *
     * @param Node\Expr $expr
     * @param Node\Arg|null $arg
     * @param Node|null $node
     * @return int|string
     */
    protected function handleArgumentValue(Node\Expr $expr)
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        } elseif ($expr instanceof Node\Expr\ConstFetch) {
            return $expr->name->parts[0];
        } elseif ($expr instanceof Node\Expr\ClassConstFetch) {

            /**
             * @var $fullyQualified Node\Name\FullyQualified
             */
            $fullyQualified = $expr->class;

            return implode('\\', $fullyQualified->parts) . '::' . $expr->name;
        } elseif ($expr instanceof Node\Scalar\LNumber) {
            return $expr->value;
        } elseif ($expr instanceof Node\Scalar\DNumber) {
            return $expr->value;
        } elseif ($expr instanceof Node\Expr\BinaryOp\Concat) {
            $concatValue = '';

            // Executing it two times, for left and right recursively
            $this->handleConcatExpr($expr, $concatValue);
            $this->handleConcatExpr($expr, $concatValue);

            return $concatValue;
        } elseif ($expr instanceof Node\Expr\ArrayDimFetch) {
            return 'user-defined';
        } elseif ($expr instanceof Node\Expr\FuncCall) {
            return 'function-call';
        } elseif ($expr instanceof Node\Expr\Variable) {
            return '$' . $expr->name;
        } elseif ($expr instanceof Node\Expr\Cast\Int_) {
            return $this->handleArgumentValue($expr->expr);
        } elseif ($expr instanceof Node\Expr\Array_) {
            $defaultValue = '[';
            $defaultValueItems = [];
            foreach ($expr->items as $item) {
                if ($item->key === null) {
                    if ($item->value instanceof Node\Scalar\String_) {
                        $defaultValueItems[] = "'" . $item->value->value . "'";
                    } elseif ($item->value instanceof Node\Scalar\LNumber) {
                        $defaultValueItems[] = "'" . $item->value->value . "'";
                    } elseif ($item->value instanceof Node\Scalar\DNumber) {
                        $defaultValueItems[] = "'" . $item->value->value . "'";
                    }
                } elseif ($item->key instanceof Node\Scalar\String_) {
                    $defaultValueItems[] = "'" . $item->key->value . "'";
                } elseif ($item->key instanceof Node\Scalar\LNumber) {
                    $defaultValueItems[] = "'" . $item->key->value . "'";
                } elseif ($item->key instanceof Node\Scalar\DNumber) {
                    $defaultValueItems[] = "'" . $item->key->value . "'";
                }
            }
            $defaultValue .= implode(', ', $defaultValueItems);
            $defaultValue .= ']';

            if (!empty($defaultValueItems)) {
                return $defaultValue;
            }

            return 'array';
        } elseif ($expr instanceof Node\Expr\Isset_) {
            return $this->handleArgumentValue($expr->vars[0]);
        } elseif ($expr instanceof Node\Expr\Ternary) {
            return 'user-defined';
        } elseif ($expr instanceof Node\Expr\Cast\Bool_) {
            return $this->handleArgumentValue($expr->expr);
        } elseif ($expr instanceof Node\Expr\MethodCall) {
            if ($expr->var instanceof Node\Expr\New_) {
                /**
                 * @var $fullyQualified Node\Name\FullyQualified
                 */
                $fullyQualified = $expr->var->class;

                return implode('\\', $fullyQualified->parts) . '->' . $expr->name;
            }

            if ($expr->var instanceof Node\Expr\Variable) {
                return '$' . $expr->var->name . '->' . $expr->name;
            }

            return 'method-call';
        } elseif ($expr instanceof Node\Expr\StaticCall) {
            $defaultValueArgs = [];
            foreach ($expr->args as $staticCallArg) {
                if ($staticCallArg->value instanceof Node\Expr\Array_) {
                    $defaultValue = '[';
                    $defaultValueItems = [];
                    foreach ($staticCallArg->value->items as $item) {
                        if ($item->key === null) {
                            if ($item->value instanceof Node\Scalar\String_) {
                                $defaultValueItems[] = "'" . $item->value->value . "'";
                            } elseif ($item->value instanceof Node\Scalar\LNumber) {
                                $defaultValueItems[] = "'" . $item->value->value . "'";
                            } elseif ($item->value instanceof Node\Scalar\DNumber) {
                                $defaultValueItems[] = "'" . $item->value->value . "'";
                            }
                        } elseif ($item->key instanceof Node\Scalar\String_) {
                            $defaultValueItems[] = "'" . $item->key->value . "'";
                        } elseif ($item->key instanceof Node\Scalar\LNumber) {
                            $defaultValueItems[] = "'" . $item->key->value . "'";
                        } elseif ($item->key instanceof Node\Scalar\DNumber) {
                            $defaultValueItems[] = "'" . $item->key->value . "'";
                        }
                    }
                    $defaultValue .= implode(', ', $defaultValueItems);
                    $defaultValue .= ']';

                    $defaultValueArgs[] = $defaultValue;
                }
            }

            $staticCallArguments = implode(',', $defaultValueArgs);

            return implode('\\', $expr->class->parts) . '::' . $expr->name . '(' . $staticCallArguments . ')';
        } elseif ($expr instanceof Node\Expr\PropertyFetch) {
            return '$' . $expr->var->name . '->' . $expr->name;
        }

        return 0;
    }

    /**
     * @param Node\Expr\BinaryOp\Concat $expr
     * @param string $concatValue
     * @return string
     */
    protected function handleConcatExpr(Node\Expr\BinaryOp\Concat $expr, &$concatValue = '')
    {
        if ($expr->left === null && $expr->right === null) {
            return $concatValue;
        }

        if (
            $expr->left instanceof Node\Expr\BinaryOp\Concat
        ) {
            $return = $this->handleConcatExpr($expr->left, $concatValue);
            unset($expr->left);
            return $return;
        }

        if (
            $expr->right instanceof Node\Expr\BinaryOp\Concat
        ) {
            $return = $this->handleConcatExpr($expr->right, $concatValue);
            unset($expr->right);
            return $return;
        }

        $left = '';
        $right = '';

        if ($expr->left instanceof Node\Expr) {
            $left = $this->handleArgumentValue($expr->left);
        }

        if ($expr->right instanceof Node\Expr) {
            $right = $this->handleArgumentValue($expr->right);
        }

        $concatValue .= $left . $right;
    }
}
