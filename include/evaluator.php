<?php

require_once __DIR__ . '/error.php';
require_once __DIR__ . '/localization.php';

define('EV_MAX_VALUE', 2000000000);
define('EV_MIN_VALUE', -2000000000);

define('EV_LEXEM_VALUE', 0);
define('EV_LEXEM_UNARY_MINUS', 1);
define('EV_LEXEM_NOT', 2);
define('EV_LEXEM_PLUS', 3);
define('EV_LEXEM_MINUS', 4);
define('EV_LEXEM_MULTIPLY', 5);
define('EV_LEXEM_DIVIDE', 6);
define('EV_LEXEM_POWER', 7);
define('EV_LEXEM_OPEN_BRACKET', 8);
define('EV_LEXEM_CLOSE_BRACKET', 9);
define('EV_LEXEM_COMMA', 10);
define('EV_LEXEM_QUESTION', 11);
define('EV_LEXEM_COLUMN', 12);
define('EV_LEXEM_FUNC', 13);
define('EV_LEXEM_GREATER', 14);
define('EV_LEXEM_LESS', 15);
define('EV_LEXEM_GREATER_EQUAL', 16);
define('EV_LEXEM_LESS_EQUAL', 17);
define('EV_LEXEM_EQUAL', 18);
define('EV_LEXEM_NOT_EQUAL', 19);
define('EV_LEXEM_AND', 20);
define('EV_LEXEM_OR', 21);
define('EV_LEXEM_INVALID', 22);

//------------------------------------------------------------------------------------------------
// Functions
//------------------------------------------------------------------------------------------------
abstract class EvFunction
{
	public abstract function evaluate($evaluator, $args);
	public abstract function name();
	public function is_deterministic() { return true; }
}

class EvFuncRound extends EvFunction
{
	public function evaluate($evaluator, $args)
	{
		switch (count($args))
		{
		case 0:
			return 0;
		case 1:
			return round($args[0]->evaluate());
		default:
			return round($args[0]->evaluate(), $args[1]->evaluate());
		}
	}
	
	public function name()
	{
		return 'round';
	}
}

class EvFuncFloor extends EvFunction
{
	public function evaluate($evaluator, $args)
	{
		if (isset($args[0]))
		{
			return floor($args[0]->evaluate());
		}
		return 0;
	}
	
	public function name()
	{
		return 'floor';
	}
}

class EvFuncCeil extends EvFunction
{
	public function evaluate($evaluator, $args)
	{
		if (isset($args[0]))
		{
			return ceil($args[0]->evaluate());
		}
		return 0;
	}
	
	public function name()
	{
		return 'ceil';
	}
}

class EvFuncLog extends EvFunction
{
	public function evaluate($evaluator, $args)
	{
		switch (count($args))
		{
		case 0:
			return 0;
		case 1:
			return log($args[0]->evaluate());
		default:
			return log($args[0]->evaluate(), $args[1]->evaluate());
		}
	}
	
	public function name()
	{
		return 'log';
	}
}

class EvFuncMin extends EvFunction
{
	public function evaluate($evaluator, $args)
	{
		$result = EV_MAX_VALUE;
		foreach ($args as $arg)
		{
			$result = min($result, $arg->evaluate());
		}
		return $result;
	}
	
	public function name()
	{
		return 'min';
	}
}

class EvFuncMax extends EvFunction
{
	public function evaluate($evaluator, $args)
	{
		$result = EV_MIN_VALUE;
		foreach ($args as $arg)
		{
			$result = max($result, $arg->evaluate());
		}
		return $result;
	}
	
	public function name()
	{
		return 'max';
	}
}

class EvFuncParam extends EvFunction
{
	public function __construct($name)
	{
		$this->name = $name;
	}
	
	public function evaluate($evaluator, $args)
	{
		$param_name = $this->name;
		if (!isset($evaluator->$param_name))
		{
			return 0;
		}
		
		$param = $evaluator->$param_name;
		for ($i = 0; $i < count($args); ++$i)
		{
			if (is_array($param))
			{
				$param_count = count($param); 
				if ($param_count == 0)
				{
					return 0;
				}
				
				$arg = floor($args[$i]->evaluate());
				if ($arg < 0)
				{
					$arg = 0;
				}
				else if ($arg >= $param_count)
				{
					$arg = $param_count - 1;
				}
				$param = $param[$arg];
			}
			else
			{
				return $param;
			}
		}
		
		while (is_array($param))
		{
			if (count($param) == 0)
			{
				return 0;
			}
			$param = $param[0];
		}
		return $param;
	}
	
	public function is_deterministic() 
	{
		return false;
	}
	
	public function name()
	{
		return $this->name;
	}
}

//------------------------------------------------------------------------------------------------
// Nodes
//------------------------------------------------------------------------------------------------
class EvNode
{
	public function __construct($evaluator, $type, $position, $prev)
	{
		$this->type = $type;
		$this->position = $position;
		$this->prev = $prev;
		$this->next = NULL;
		if ($prev)
		{
			$prev->next = $this;
		}
		$this->evaluator = $evaluator;
	}
	
	public function complete()
	{
	}
	
	public function is_complete()
	{
		return
			$this->type == EV_LEXEM_VALUE ||
			$this->type == EV_LEXEM_CLOSE_BRACKET ||
			$this->type == EV_LEXEM_FUNC;
	}
	
	public function optimize()
	{
		return $this;
	}
	
	public function to_lexem()
	{
		switch ($this->type)
		{
		case EV_LEXEM_VALUE:
			return $this->value;
		case EV_LEXEM_EQUAL:
			return '=';
		case EV_LEXEM_NOT_EQUAL:
			return '!=';
		case EV_LEXEM_AND:
			return '&&';
		case EV_LEXEM_OR:
			return '||';
		case EV_LEXEM_GREATER:
			return '>';
		case EV_LEXEM_LESS:
			return '<';
		case EV_LEXEM_GREATER_EQUAL:
			return '>=';
		case EV_LEXEM_LESS_EQUAL:
			return '<=';
		case EV_LEXEM_PLUS:
			return '+';
		case EV_LEXEM_UNARY_MINUS:
		case EV_LEXEM_MINUS:
			return '-';
		case EV_LEXEM_NOT:
			return '!';
		case EV_LEXEM_MULTIPLY:
			return '*';
		case EV_LEXEM_DIVIDE:
			return '/';
		case EV_LEXEM_POWER:
			return '^';
		case EV_LEXEM_OPEN_BRACKET:
			return '(';
		case EV_LEXEM_CLOSE_BRACKET:
			return ')';
		case EV_LEXEM_COMMA:
			return ',';
		case EV_LEXEM_QUESTION:
			return '?';
		case EV_LEXEM_COLUMN:
			return ':';
		case EV_LEXEM_FUNC:
			if (isset($this->index))
			{
				return $this->evaluator->functions[$this->index]->name();
			}
			return  'unknown function';
		}
		return  'unknown lexem';
	}
	
	public function to_string($indent = 0)
	{
		$result = str_pad('', $indent, "\t") . $this->position . ': ';
		switch ($this->type)
		{
		case EV_LEXEM_VALUE:
			$result .= $this->value;
			break;
		case EV_LEXEM_EQUAL:
			$result .= '=';
			break;
		case EV_LEXEM_NOT_EQUAL:
			$result .= '!=';
			break;
		case EV_LEXEM_AND:
			$result .= '&&';
			break;
		case EV_LEXEM_OR:
			$result .= '||';
			break;
		case EV_LEXEM_GREATER:
			$result .= '>';
			break;
		case EV_LEXEM_LESS:
			$result .= '<';
			break;
		case EV_LEXEM_GREATER_EQUAL:
			$result .= '>=';
			break;
		case EV_LEXEM_LESS_EQUAL:
			$result .= '<=';
			break;
		case EV_LEXEM_PLUS:
			$result .= '+';
			break;
		case EV_LEXEM_UNARY_MINUS:
		case EV_LEXEM_MINUS:
			$result .= '-';
			break;
		case EV_LEXEM_NOT:
			$result .= '!';
			break;
		case EV_LEXEM_MULTIPLY:
			$result .= '*';
			break;
		case EV_LEXEM_DIVIDE:
			$result .= '/';
			break;
		case EV_LEXEM_POWER:
			$result .= '^';
			break;
		case EV_LEXEM_OPEN_BRACKET:
			$result .= '(';
			break;
		case EV_LEXEM_CLOSE_BRACKET:
			$result .= ')';
			break;
		case EV_LEXEM_COMMA:
			$result .= ',';
			break;
		case EV_LEXEM_QUESTION:
			$result .= '?';
			break;
		case EV_LEXEM_COLUMN:
			$result .= ':';
			break;
		case EV_LEXEM_FUNC:
			if (isset($this->index))
			{
				$result .= $this->evaluator->functions[$this->index]->name();
			}
			else
			{
				$result .= 'unknown function';
			}
			break;
		}
		return $result;
	}
	
	public function evaluate()
	{
		throw new Exc('Evaluating non evaluatable node: ' . $this->evaluator->highlight_node($this));
	}
	
	protected function find_close_bracket($open_bracket, $alt_type = EV_LEXEM_CLOSE_BRACKET)
	{
		$inner_bracket = 0;
		for ($node = $open_bracket->next; $node; $node = $node->next)
		{
			if ($node->type == EV_LEXEM_OPEN_BRACKET)
			{
				++$inner_bracket;
			}
			else if ($inner_bracket > 0)
			{
				if ($node->type == EV_LEXEM_CLOSE_BRACKET)
				{
					--$inner_bracket;
				}
			}
			else if ($node->type == EV_LEXEM_CLOSE_BRACKET || $node->type == $alt_type)
			{
				return $node;
			}
		}
		throw new Exc(get_label('Bracket is not closed: [0]', $this->evaluator->highlight_node($open_bracket)));
	}
	
}

class EvValueNode extends EvNode
{
	public function __construct($evaluator, $value, $position, $prev)
	{
		parent::__construct($evaluator, EV_LEXEM_VALUE, $position, $prev);
		
		$this->value = $value;
	}
	
	public function evaluate()
	{
		if ($this->type != EV_LEXEM_VALUE)
		{
			throw new Exc('Lexem is not a unary operation: '.$this->evaluator->highlight_node($this));
		}
		return $this->value;
	}
}

class EvUnaryOpNode extends EvNode
{
	public function __construct($evaluator, $type, $position, $prev)
	{
		parent::__construct($evaluator, $type, $position, $prev);
	}
	
	public function complete()
	{
		if (!isset($this->child))
		{
			if (!$this->next)
			{
				$this->evaluator->unexpectedLexem($this);
			}
			if (!$this->next->is_complete())
			{
				$this->next->complete();
			}
			$this->child = $this->next;
			if ($this->child->next)
			{
				$this->child->next->prev = $this;
			}
			$this->next = $this->child->next;
			$this->child->prev = $this->child->next = NULL;
		}
	}
	
	public function is_complete()
	{
		return isset($this->child);
	}
	
	public function evaluate()
	{
		switch ($this->type)
		{
		case EV_LEXEM_UNARY_MINUS:
			return -$this->child->evaluate();
		case EV_LEXEM_NOT:
			return $this->child->evaluate() ? 0 : 1;
		}
		throw new Exc('Lexem is not a unary operation: '.$this->evaluator->highlight_node($this));
	}
	
	public function to_string($indent = 0)
	{
		$result = parent::to_string($indent);
		if (isset($this->child))
		{
			$result .= "\n" . $this->child->to_string($indent + 1);
		}
		return $result;
	}
	
	public function optimize()
	{
		if (isset($this->child))
		{
			$child = $this->child->optimize();
			if ($child)
			{
				$this->child = $child;
				$child->value = $this->evaluate();
				return $child;
			}
		}
		return NULL;
	}
}

class EvBinaryOpNode extends EvNode
{
	public function __construct($evaluator, $type, $position, $prev)
	{
		parent::__construct($evaluator, $type, $position, $prev);
	}
	
	public function complete()
	{
		if (!isset($this->left))
		{
			if (!$this->prev)
			{
				$this->evaluator->unexpectedLexem($this);
			}
			$this->left = $this->prev;
			if ($this->left->prev)
			{
				$this->left->prev->next = $this;
			}
			else
			{
				$this->evaluator->node = $this;
			}
			$this->prev = $this->left->prev;
			$this->left->prev = $this->left->next = NULL;
		}
		
		if (!isset($this->right))
		{
			if (!$this->next)
			{
				$this->evaluator->unexpectedLexem($this);
			}
			$this->right = $this->next;
			if ($this->right->next)
			{
				$this->right->next->prev = $this;
			}
			$this->next = $this->right->next;
			$this->right->prev = $this->right->next = NULL;
		}
	}
	
	public function is_complete()
	{
		return isset($this->left) && isset($this->right);
	}
	
	public function evaluate()
	{
		$result = 0;
		switch ($this->type)
		{
		case EV_LEXEM_EQUAL:
			$result = $this->left->evaluate() == $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_NOT_EQUAL:
			$result = $this->left->evaluate() != $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_AND:
			$result = $this->left->evaluate() && $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_OR:
			$result = $this->left->evaluate() || $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_GREATER:
			$result = $this->left->evaluate() > $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_LESS:
			$result = $this->left->evaluate() < $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_GREATER_EQUAL:
			$result = $this->left->evaluate() >= $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_LESS_EQUAL:
			$result = $this->left->evaluate() <= $this->right->evaluate() ? 1 : 0;
			break;
		case EV_LEXEM_PLUS:
			$result = $this->left->evaluate() + $this->right->evaluate();
			break;
		case EV_LEXEM_MINUS:
			$result = $this->left->evaluate() - $this->right->evaluate();
			break;
		case EV_LEXEM_MULTIPLY:
			$result = $this->left->evaluate() * $this->right->evaluate();
			break;
		case EV_LEXEM_DIVIDE:
			$right = $this->right->evaluate();
			$left = $this->left->evaluate();
			if ($right != 0)
			{
				$result = $left / $right;
			}
			else if ($left > 0)
			{
				$result = EV_MAX_VALUE;
			}
			else if ($left < 0)
			{
				$result = EV_MIN_VALUE;
			}
			else
			{
				$result = 0;
			}
			break;
		case EV_LEXEM_POWER:
			$result = pow($this->left->evaluate(), $this->right->evaluate());
			break;
		default:
			throw new Exc('Lexem is not a binary operation: '.$this->evaluator->highlight_node($this));
		}
		//echo $this->position.': '.$this->left->evaluate().$this->to_lexem().$this->right->evaluate().'='.$result.'<br>';
		return $result;
	}
	
	public function to_string($indent = 0)
	{
		$result = parent::to_string($indent);
		if (isset($this->left))
		{
			$result .= "\n" . $this->left->to_string($indent + 1);
		}
		if (isset($this->right))
		{
			$result .= "\n" . $this->right->to_string($indent + 1);
		}
		return $result;
	}
	
	public function optimize()
	{
		if (isset($this->left))
		{
			$left = $this->left->optimize();
			if ($left)
			{
				$this->left = $left;
			}
		}
		
		if (isset($this->right))
		{
			$right = $this->right->optimize();
			if ($right)
			{
				$this->right = $right;
			}
		}
		
		if ($left && $right)
		{
			$left->value = $this->evaluate();
			return $left;
		}
		return NULL;
	}
}

class EvTernaryOpNode extends EvNode
{
	public function __construct($evaluator, $type, $position, $prev)
	{
		parent::__construct($evaluator, $type, $position, $prev);
	}
	
	public function complete()
	{
		if (!isset($this->first))
		{
			if (!$this->prev)
			{
				$this->evaluator->unexpectedLexem($this);
			}
			$this->first = $this->prev;
			if ($this->first->prev)
			{
				$this->first->prev->next = $this;
			}
			else
			{
				$this->evaluator->node = $this;
			}
			$this->prev = $this->first->prev;
			$this->first->prev = $this->first->next = NULL;
		}
		
		if (!isset($this->second))
		{
			if (!$this->next)
			{
				$this->evaluator->unexpectedLexem($this);
			}
			$this->second = $this->next;
			if ($this->second->next)
			{
				$this->second->next->prev = $this;
			}
			$this->next = $this->second->next;
			$this->second->prev = $this->second->next = NULL;
		}
		
		if (!isset($this->third))
		{
			if (!$this->next)
			{
				$this->evaluator->unexpectedLexem($this);
			}
			if ($this->next->type != EV_LEXEM_COLUMN)
			{
				$this->evaluator->unexpectedLexem($this->next);
			}
			if (!$this->next->next)
			{
				$this->evaluator->unexpectedLexem($this->next);
			}
			
			$this->third = $this->next->next;
			if ($this->third->next)
			{
				$this->third->next->prev = $this;
			}
			$this->next->next = $this->next->prev = NULL;
			$this->next = $this->third->next;
			$this->third->prev = $this->third->next = NULL;
		}		
	}
	
	public function is_complete()
	{
		return isset($this->first) && isset($this->second) && isset($this->third);
	}
	
	public function evaluate()
	{
		if ($this->type != EV_LEXEM_QUESTION)
		{
			throw new Exc('Lexem is not a ternary operation: '.$this->evaluator->highlight_node($this));
		}
		return $this->first->evaluate() ? $this->second->evaluate() : $this->third->evaluate();
	}
	
	public function to_string($indent = 0)
	{
		$result = parent::to_string($indent);
		if (isset($this->first))
		{
			$result .= "\n" . $this->first->to_string($indent + 1);
		}
		if (isset($this->second))
		{
			$result .= "\n" . $this->second->to_string($indent + 1);
		}
		if (isset($this->third))
		{
			$result .= "\n" . $this->third->to_string($indent + 1);
		}
		return $result;
	}
	
	public function optimize()
	{
		if (isset($this->first))
		{
			$first = $this->first->optimize();
			if ($first)
			{
				$this->first = $first;
			}
		}
		
		if (isset($this->second))
		{
			$second = $this->second->optimize();
			if ($second)
			{
				$this->second = $second;
			}
		}
		
		if (isset($this->thrird))
		{
			$thrird = $this->thrird->optimize();
			if ($thrird)
			{
				$this->thrird = $thrird;
			}
		}
		
		if ($first && $second && $thrird)
		{
			$first->value = $this->evaluate();
			return $first;
		}
		return NULL;
	}
}

class EvFuncNode extends EvNode
{
	public function __construct($evaluator, $index, $position, $prev)
	{
		parent::__construct($evaluator, EV_LEXEM_FUNC, $position, $prev);
		$this->index = $index;
	}
	
	public function complete()
	{
		if (!$this->next)
		{
			$this->evaluator->unexpectedLexem($this);
		}
		
		if ($this->next->type != EV_LEXEM_OPEN_BRACKET)
		{
			$this->evaluator->unexpectedLexem($this->next);
		}
		
		$this->args = array();
		$open_bracket = $this->next;
		while ($open_bracket->type != EV_LEXEM_CLOSE_BRACKET)
		{
			$close_bracket = $this->find_close_bracket($open_bracket, EV_LEXEM_COMMA);
			$next = $open_bracket->next;
			if ($open_bracket->prev)
			{
				$open_bracket->prev->next = $close_bracket;
			}
			else
			{
				$this->evaluator->node = $close_bracket;
			}
			if ($close_bracket->prev)
			{
				$close_bracket->prev->next = NULL;
			}
			$close_bracket->prev = $open_bracket->prev;
			
			$open_bracket->prev = NULL;
			if ($open_bracket->next)
			{
				$open_bracket->next->prev = NULL;
			}
				
			if ($next != $close_bracket)
			{
				$this->args[] = $open_bracket->next;
			}
			
			$open_bracket = $close_bracket;
		}
		
		if ($open_bracket->prev)
		{
			$open_bracket->prev->next = $open_bracket->next;
		}
		else
		{
			$this->evaluator->node = $open_bracket->next;
		}
		if ($open_bracket->next)
		{
			$open_bracket->next->prev = $open_bracket->prev;
		}
		$open_bracket->prev = $open_bracket->next = NULL;
		
		$original_node = $this->evaluator->node;
		for ($i = 0; $i < count($this->args); ++$i)
		{
			$this->evaluator->node = $this->args[$i];
			$this->evaluator->convert_to_tree();
			$this->args[$i] = $this->evaluator->node;
		}
		$this->evaluator->node = $original_node;
	}
	
	public function is_complete()
	{
		return isset($this->args);
	}
	
	public function evaluate()
	{
		$result = $this->evaluator->functions[$this->index]->evaluate($this->evaluator, $this->args);
		// echo $this->position.': '.$this->evaluator->functions[$this->index]->name();
		// $delim ='(';
		// foreach($this->args as $arg)
		// {
			// echo $delim.$arg->evaluate();
			// $delim = ',';
		// }
		// echo ')='.$result.'<br>';
		return $result;
	}
	
	public function to_string($indent = 0)
	{
		$result = parent::to_string($indent);
		if (isset($this->args))
		{
			foreach ($this->args as $arg)
			{
				$result .= "\n" . $arg->to_string($indent + 1);
			}
		}
		return $result;
	}
	
	public function optimize()
	{
		$can_optimize = $this->evaluator->functions[$this->index]->is_deterministic();
		if (isset($this->args))
		{
			for ($i = 0; $i < count($this->args); ++$i)
			{
				$arg = $this->args[$i]->optimize();
				if ($arg)
				{
					$this->args[$i] = $arg;
				}
				else
				{
					$can_optimize = false;
				}
			}
		}
		if ($can_optimize)
		{
			return new EvValueNode($this->evaluator, $this->evaluate(), $this->position, NULL);
		}
		return NULL;
	}
}

class EvBracketNode extends EvNode
{
	public function __construct($evaluator, $position, $prev)
	{
		parent::__construct($evaluator, EV_LEXEM_OPEN_BRACKET, $position, $prev);
	}
	
	public function complete()
	{
		if ($this->type != EV_LEXEM_OPEN_BRACKET)
		{
			$this->evaluator->unexpectedLexem($this->next);
		}
		
		$close_bracket = $this->find_close_bracket($this);
		
		$prev = $this->prev;
		if ($prev)
		{
			$prev->next = $close_bracket->next;
		}
		else
		{
			$this->evaluator->node = $close_bracket->next;
		}
		if ($close_bracket->next)
		{
			$close_bracket->next->prev = $prev;
		}
		
		$this->next->prev = NULL;
		if ($close_bracket->prev)
		{
			$close_bracket->prev->next = NULL;
		}
		
		$original_node = $this->evaluator->node;
		$this->evaluator->node = $this->next;
		$this->evaluator->convert_to_tree();
		
		$this->evaluator->node->prev = $prev;
		if ($prev)
		{
			$this->evaluator->node->next = $prev->next;
			$this->evaluator->node->prev = $prev;
			if ($prev->next)
			{
				$prev->next->prev = $this->evaluator->node;
			}
			$prev->next = $this->evaluator->node;
		}
		else if ($original_node)
		{
			$this->evaluator->node->next = $original_node;
			$this->evaluator->node->prev = NULL;
			$original_node->prev = $this->evaluator->node;
			$original_node = $this->evaluator->node;
		}
		else
		{
			$original_node = $this->evaluator->node;
			$original_node->next = $original_node->prev = NULL;
		}
		$this->evaluator->node = $original_node;
	}
}

//------------------------------------------------------------------------------------------------
// Evaluator
//------------------------------------------------------------------------------------------------
class Evaluator
{
	public function __construct($expr, &$functions)
	{
		$this->functions = &$functions;
		$this->parse($expr);
	}
	
	public function parse($expr)
	{
		$this->expr = $expr;
		
		$index = 0;
		$node = $this->node = $this->parse_next_lexem($expr, $index, NULL);
		while ($node)
		{
			$node = $this->parse_next_lexem($expr, $index, $node);
		}
		//$this->print_nodes();
		$this->convert_to_tree();
		//$this->print_nodes();
		$this->optimize();
	}
	
	public function evaluate()
	{
		if (!isset($this->node))
		{
			return 0;
		}
		return max(min($this->node->evaluate(), EV_MAX_VALUE), EV_MIN_VALUE);
	}
	
	public function optimize()
	{
		for ($node = $this->node; $node; $node = $node->next)
		{
			$n = $node->optimize();
			if ($n)
			{
				$n->prev = $node->prev;
				$n->next = $node->next;
				if ($n->prev)
				{
					$n->prev->next = $n;
				}
				else
				{
					$this->node = $n;
				}
				if ($n->next)
				{
					$n->next->prev = $n;
				}
				$node = $n;
			}
		}
	}
	
	public function highlight_node($node)
	{
		$len = strlen($node->to_lexem());
		return 
			substr($this->expr, 0, $node->position).
			'<b><big> '.
			substr($this->expr, $node->position, $len).
			' </big></b>'.
			substr($this->expr, $node->position + $len);
	}
	
	public function unexpectedLexem($node)
	{
		throw new Exc(get_label('Unexpected lexem: [0]', $this->highlight_node($node)));
	}
	
	private function find_node($type1, $type2, $type3, $type4)
	{
		for ($node = $this->node; $node; $node = $node->next)
		{
			if (!$node->is_complete() && ($node->type == $type1 || $node->type == $type2 || $node->type == $type3 || $node->type == $type4))
			{
				return $node;
			}
		}
		return NULL;
	}
	
	private function complete_lexems($type1, $type2 = EV_LEXEM_INVALID, $type3 = EV_LEXEM_INVALID, $type4 = EV_LEXEM_INVALID)
	{
		while (!is_null($node = $this->find_node($type1, $type2, $type3, $type4)))
		{
			$node->complete();
		}
		return is_null($this->node->next) && $this->node->is_complete();
	}
	
	public function convert_to_tree()
	{
		if ($this->complete_lexems(EV_LEXEM_FUNC)) { return; }
		if ($this->complete_lexems(EV_LEXEM_OPEN_BRACKET)) { return; }
		if ($this->complete_lexems(EV_LEXEM_UNARY_MINUS, EV_LEXEM_NOT)) { return; }
		if ($this->complete_lexems(EV_LEXEM_POWER)) { return; }
		if ($this->complete_lexems(EV_LEXEM_MULTIPLY, EV_LEXEM_DIVIDE)) { return; }
		if ($this->complete_lexems(EV_LEXEM_PLUS, EV_LEXEM_MINUS)) { return; }
		if ($this->complete_lexems(EV_LEXEM_GREATER, EV_LEXEM_LESS, EV_LEXEM_GREATER_EQUAL, EV_LEXEM_LESS_EQUAL)) { return; }
		if ($this->complete_lexems(EV_LEXEM_EQUAL, EV_LEXEM_NOT_EQUAL)) { return; }
		if ($this->complete_lexems(EV_LEXEM_EQUAL, EV_LEXEM_NOT_EQUAL)) { return; }
		if ($this->complete_lexems(EV_LEXEM_AND)) { return; }
		if ($this->complete_lexems(EV_LEXEM_OR)) { return; }
		if ($this->complete_lexems(EV_LEXEM_QUESTION)) { return; }
		Evaluator::unexpectedLexem($this->node);
	}
	
	private function parse_next_lexem($expr, &$index, $prevNode)
	{
		while ($index < strlen($expr))
		{
			$c = $expr[$index];
			if ($c != ' ' && $c != "\t")
			{
				break;
			}
			++$index;
		}
		
		if ($index >= strlen($expr))
		{
			return NULL;
		}
		
		$node = NULL;
		if (is_numeric($c))
		{
			$position = $index;
			$value = (int)$c;
			while (++$index < strlen($expr))
			{
				$c = $expr[$index];
				if (!is_numeric($c))
				{
					break;
				}
				$value = $value * 10 + (int)$c;
			}
			if ($c == '.')
			{
				$fraction = 10;
				while (++$index < strlen($expr))
				{
					$c = $expr[$index];
					if (!is_numeric($c))
					{
						break;
					}
					$value += (float)$c / $fraction;
					$fraction *= 10;
				}
			}
			$node = new EvValueNode($this, $value, $position, $prevNode);
		}
		else if ($c == '!')
		{
			if ($index + 1 < strlen($expr) && $expr[$index + 1] == '=')
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_NOT_EQUAL, $index, $prevNode);
				$index += 2;
			}
			else
			{
				$node = new EvUnaryOpNode($this, EV_LEXEM_NOT, $index, $prevNode);
				++$index;
			}
		}
		else if ($c == '&')
		{
			if ($index + 1 < strlen($expr) && $expr[$index + 1] == '&')
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_AND, $index, $prevNode);
				$index += 2;
			}
			else
			{
				throw new Exc(get_label('Unexpected lexem: [0]', $index));
			}
		}
		else if ($c == '|')
		{
			if ($index + 1 < strlen($expr) && $expr[$index + 1] == '|')
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_OR, $index, $prevNode);
				$index += 2;
			}
			else
			{
				throw new Exc(get_label('Unexpected lexem: [0]', $index));
			}
		}
		else if ($c == '=')
		{
			$node = new EvBinaryOpNode($this, EV_LEXEM_EQUAL, $index, $prevNode);
			++$index;
			if ($index < strlen($expr) && $expr[$index] == '=')
			{
				++$index;
			}
		}
		else if ($c == '>')
		{
			if ($index + 1 < strlen($expr) && $expr[$index + 1] == '=')
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_GREATER_EQUAL, $index, $prevNode);
				$index += 2;
			}
			else
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_GREATER, $index, $prevNode);
				++$index;
			}
		}
		else if ($c == '<')
		{
			if ($index + 1 < strlen($expr) && $expr[$index + 1] == '=')
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_LESS_EQUAL, $index, $prevNode);
				$index += 2;
			}
			else
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_LESS, $index, $prevNode);
				++$index;
			}
		}
		else if ($c == '+')
		{
			$node = new EvBinaryOpNode($this, EV_LEXEM_PLUS, $index, $prevNode);
			++$index;
		}
		else if ($c == '-')
		{
			if ($prevNode && $prevNode->is_complete())
			{
				$node = new EvBinaryOpNode($this, EV_LEXEM_MINUS, $index, $prevNode);
			}
			else
			{
				$node = new EvUnaryOpNode($this, EV_LEXEM_UNARY_MINUS, $index, $prevNode);
			}
			++$index;
		}
		else if ($c == '*')
		{
			$node = new EvBinaryOpNode($this, EV_LEXEM_MULTIPLY, $index, $prevNode);
			++$index;
		}
		else if ($c == '/')
		{
			$node = new EvBinaryOpNode($this, EV_LEXEM_DIVIDE, $index, $prevNode);
			++$index;
		}
		else if ($c == '^')
		{
			$node = new EvBinaryOpNode($this, EV_LEXEM_POWER, $index, $prevNode);
			++$index;
		}
		else if ($c == '(')
		{
			$node = new EvBracketNode($this, $index, $prevNode);
			++$index;
		}
		else if ($c == ')')
		{
			$node = new EvNode($this, EV_LEXEM_CLOSE_BRACKET, $index, $prevNode);
			++$index;
		}
		else if ($c == ',')
		{
			$node = new EvNode($this, EV_LEXEM_COMMA, $index, $prevNode);
			++$index;
		}
		else if ($c == '?')
		{
			$node = new EvTernaryOpNode($this, EV_LEXEM_QUESTION, $index, $prevNode);
			++$index;
		}
		else if ($c == ':')
		{
			$node = new EvNode($this, EV_LEXEM_COLUMN, $index, $prevNode);
			++$index;
		}
		else
		{
			for ($i = 0; $i < count($this->functions); ++$i)
			{
				$func_name = $this->functions[$i]->name();
				if (stripos($expr, $func_name, $index) === $index)
				{
					$index += strlen($func_name);
					$node = new EvFuncNode($this, $i, $index, $prevNode);
				}
			}
			if (is_null($node))
			{
				throw new Exc(get_label('Unexpected lexem: [0]', $index));
			}
		}
		return $node;
	}
	
	public function print_nodes()
	{
		echo '-----------------------------------------<br>';
		if (!isset($this->node))
		{
			return;
		}
		
		$node = $this->node;
		echo '<pre>';
		while ($node)
		{
			echo $node->to_string() . "\n";
			$node = $node->next;
		}
		echo '</pre>';
	}
}

?>