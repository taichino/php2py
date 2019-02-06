#!/usr/bin/php
<?php

function expr($a) { return $a; }
function str_mul($str, $n) {
  $res = "";
  for ($i = 0; $i < $n; $i++) {
    $res .= $str;
  }
  return $res;
}

class NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    return false;
  }
  function post_process($cur, $context, $contextData) {
  }
  function comment_process($cur, $context, $contextData) {
    if (get_class($cur) != 'DOMElement') {
      return;
    }
    $attrs = $cur->firstChild;
    if ($attrs->nodeName != 'attrs') return;
    $attr = expr($attrs->getElementsByTagName('attr'))->item(0);
    if (!$attr) return;
    $cur = $attr;
    if ($cur->getAttribute('key') == "phc.comments") {
      $strings = $cur->getElementsByTagName('string');
      for ($i=0; $i < $strings->length; $i++) {
        $stringNode = $strings->item($i);
        if (strlen(trim($stringNode->nodeValue)) > 0) {
          $encoding = $stringNode->getAttribute('encoding');
          $value   = ($encoding == 'base64')? base64_decode($stringNode->nodeValue) : $stringNode->nodeValue;
          $comment = ereg_replace('^//(.+)$', '#\1' . "\n", $value);
          if ($value == $comment) {
            $comment = ereg_replace('^/\*(.+)\*/', '"""\1"""' . "\n", $comment);
          }
          $context->write($comment);
        }
      }
    }
  }  
}

class ValueProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $target = expr($cur->getElementsByTagName('value'))->item(0);
    $encoding = $target->getAttribute('encoding');
    $value = ($encoding == 'base64')? base64_decode($target->nodeValue) : $target->nodeValue;
    $context->write( $value);
    return false;
  }
}

class IntProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $target = expr($cur->getElementsByTagName('value'))->item(0);
    $context->write( $target->nodeValue);
    return false;
  }
}

class RealProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $target = expr($cur->getElementsByTagName('value'))->item(0);
    $context->write(round($target->nodeValue, 3));
    return false;
  }
}

class StringProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $target = expr($cur->getElementsByTagName('value'))->item(0);
    $encoding = $target->getAttribute('encoding');
    $value = ($encoding == 'base64')? base64_decode($target->nodeValue) : $target->nodeValue;
    $value = str_replace("\n", '\n', $value);
    $context->write( "\"$value\"");
    return false;
  }
}

class ArrayProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $list = expr($cur->getElementsByTagName('Array_elem_list'))->item(0);
    $context->write(($this->is_asc($list) ? "{" : "["));
    return false;
  }
  function post_process($cur, $context, $contextData) {
    $list = expr($cur->getElementsByTagName('Array_elem_list'))->item(0);
    $context->write( $this->is_asc($list) ? "}" : "]");
  }
  function is_asc($list) {
    $result = false;
    $item = $list->firstChild;
    while ($item->nodeName == 'AST:Array_elem') {
      if ($item->firstChild->nextSibling->nodeName != 'AST:Expr') {
        $result = true;
        break;
      }
      $item = $item->nextSibling;
    }
    return $result;
  }
}

class VariableProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {

    if ($cur->firstChild->nextSibling->nodeName == 'AST:Variable') {
      $context->process_down($cur->firstChild->nextSibling);
      $context->write('.');
    }
    
    $variable = $cur->firstChild->nextSibling->nextSibling;
    $exprs = $variable->nextSibling;

    $context->process_down($variable);
    $index = $exprs->firstChild;
    do {
      if (strlen(trim($index->nodeValue)) > 0) {
        $context->write('[');
        $context->process_down($index);
        $context->write(']');
      }
      else if ($index->nodeName == 'AST:Expr') {
        $context->write('[]');
      }
      $index = $index->nextSibling;   
    } while($index);
    return true;
  }
  function post_process($cur, $context, $contextData) {
  }
}

class VariableNameProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $target = expr($cur->getElementsByTagName('value'))->item(0);
    $encoding = $target->getAttribute('encoding');
    $value = ($encoding == 'base64')? base64_decode($target->nodeValue) : $target->nodeValue;
    $value = str_replace("this", "self", $value);
    $context->write( $value);
    return false;
  }
}

class ArrayElemProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    if ($cur->firstChild->nextSibling->nodeName != 'AST:Expr') {
      $context->process_down($cur->firstChild->nextSibling);
      $context->write( ":");
      $context->process_down($cur->firstChild->nextSibling->nextSibling->nextSibling);
      return true;    
    }
  }
  function post_process($cur, $context, $contextData) {
    if ($cur->nextSibling->nodeName == 'AST:Array_elem') {
      $context->write( ', ');
    }
  }
}

class ExprProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line("", 0);
  }
}

class ReturnProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->write('return ');
    return false;
  }
}

class AssignmentProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $variable = expr($cur->getElementsByTagName('Variable'))->item(0);
    $context->process_down($variable);
    $context->write( " = ");
    $context->process_node($variable->nextSibling);
    return true;
  }
}

class OpAssignmentProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $variable = expr($cur->getElementsByTagName('Variable'))->item(0);
    $context->process_down($variable);
	$op = $variable->nextSibling;
	$context->process_down($op);
    $context->write("=");
    $context->process_node($op->nextSibling);
    return true;
  }
}

class PreOpProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->process_down($cur->firstChild->nextSibling->nextSibling);
    $context->process_down($cur->firstChild->nextSibling);
    return true;
  }
}

class OpProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $target = expr($cur->getElementsByTagName('value'))->item(0);
    $encoding = $target->getAttribute('encoding');
    $value = ($encoding == 'base64')? base64_decode($target->nodeValue) : $target->nodeValue;
    if ($value == "++") {
      $context->write("+=1");
    }
    else if ($value == "--") {
      $context->write( "-=1");
    }
    else if ($value == ".") {
      $context->write( "+");
    }
    else {
      $context->write("$value");
    }
    return false;
  }
}

class ForProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $init  = expr($cur->getElementsByTagName('Assignment'))->item(0);
    $cond  = expr($cur->getElementsByTagName('Bin_op'))->item(0);
    $post  = expr($cur->getElementsByTagName('Post_op'))->item(0);
    $stats = expr($cur->getElementsByTagName('Statement_list'))->item(0);
    $context->process_down($init);
    $context->new_line();
    $context->write( "while ");
    $context->process_down($cond);
    $context->new_line(":", 1);
    $context->process_down($stats);
    $context->process_down($post);
    return true;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line("", -1);
  }
}

class ForeachProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $vars  = $cur->getElementsByTagName('Variable');
    $stats = expr($cur->getElementsByTagName('Statement_list'))->item(0);
    $context->write( "for ");
    $context->process_down($vars->item(2));
    $context->write( " in ");
    $context->process_down($vars->item(0));
    $context->new_line(":", 1);
    $context->process_down($stats);
    return true;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line("", -1);
  }
}

class IfProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {

    $cond  = $cur->firstChild->nextSibling;
    $stats = expr($cur->getElementsByTagName('Statement_list'))->item(0);
    $this->writeBlock(true, $cond, $stats, $context);

    while (true) {
      $stats = $stats->nextSibling;
      if ($stats->nodeName == 'AST:Statement_list') {
        if (strlen(trim($stats->firstChild->nodeValue)) == 0) {
          continue;
        }
        $cond = null;
        $cur_stats = $stats;
        if ($stats->firstChild->nodeName == 'AST:If') {
          $cond = $stats->firstChild->firstChild->nextSibling;
          $stats = expr($stats->firstChild->getElementsByTagName('Statement_list'))->item(0);
        }
        $this->writeBlock(false, $cond, $stats, $context);
      }
      else {
        break;
      }
    }
    
    return true;
  }

  function writeBlock($first, $cond, $stats, $context) {
    $context->write($first?"if " : ($cond? "elif " : "else"));
    if ($cond) {
      $context->process_down($cond);
    }
    $context->new_line(":", 1);
    $context->process_down($stats);
    $context->new_line("", -1);
  }
  
}

class SwitchProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $variable = expr($cur->getElementsByTagName('Variable'))->item(0);
    $case = expr($cur->getElementsByTagName('Switch_case_list'))->item(0)->firstChild;
    do {
      $comp = $case->firstChild->nextSibling;
      if ($comp->nodeName == 'AST:Expr') {
        $context->write( "else");
      }
      else {
        if ($case->previousSibling->nodeName == 'AST:Switch_case') {
          $context->write( "elif ");
        }
        else {
          $context->write( "if ");
        }
        $context->process_down($variable);
        $context->write( "==");
        $context->process_down($comp);
      }
      $context->new_line(":", 1);
      $context->process_down(expr($case->getElementsByTagName('Statement_list'))->item(0));
      $context->new_line("", -1);
      $case = $case->nextSibling;
    } while($case->nodeName == 'AST:Switch_case');
    return true;
  }
  function post_process($cur, $context, $contextData) {
  }
}

class WhileProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->write( "while ");
    $context->process_down(expr($cur->getElementsByTagName('Bin_op'))->item(0));
    $context->new_line(":", 1);
    $context->process_down(expr($cur->getElementsByTagName('Statement_list'))->item(0));
    return true;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line("", -1);
  }
}

class DoProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->new_line("While True:", 1);
    $stats = expr($cur->getElementsByTagName('Statement_list'))->item(0);
    $context->process_down($stats);
    $context->write( "if not ");
    $context->process_down($stats->nextSibling);
    $context->new_line(":", 1);
    $context->new_line("break", -1);
    return true;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line("", -1);
  }
}

class ClassDefProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->new_line("", 0, true);
    $context->write( "class ");
    return false;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line("", -1);
  }
}

class ClassNameProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $extend = false;
    if ($cur->previousSibling->nodeName == 'AST:CLASS_NAME' && $cur->nodeValue) {
      $extend = true;
    }
    $context->write( ($extend) ? "(" : "");
    $context->write( expr($cur->getElementsByTagName('value'))->item(0)->nodeValue);
    $context->write( ($extend) ? ")" : "");
    return false;
  }
  function post_process($cur, $context, $contextData) {
    if ($cur->parentNode->nodeName == 'AST:Class_def' && $cur->nextSibling->nodeName != 'AST:CLASS_NAME') {
      $context->new_line(":", 1);
    }
  }
}

class AttributeProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $attr = expr($cur->getElementsByTagName('Name_with_default'))->item(0);
    $var  = $attr->firstChild->nextSibling;
    $init = $var->nextSibling;
    $context->process_down($var);
    $context->write('=');
    $context->process_down($init);
    return true;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line(0);
  }
}

class MethodInvocationProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $variable = false;
    foreach ($cur->childNodes as $childNode) {
      if ($childNode->nodeName == 'AST:Variable') {
        $variable = $childNode;
        break;
      }
    }
    if ($variable) {
      $context->process_down($variable);
      $context->write( ".");
    }
    $methodName = expr($cur->getElementsByTagName('METHOD_NAME'))->item(0);
    $context->process_down($methodName);
    $paramList  = expr($cur->getElementsByTagName('Actual_parameter_list'))->item(0);
    $context->process_down($paramList);
    return true;
  }
}

class MethodProcessor extends NodeProcessor {
  function post_process($cur, $context, $contextData) {
    $context->new_line("", -1, true);
  }
}

class SignatureProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->write( "def ");
    return false;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line(":", 1);
  }
}

class MethodNameProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $target = expr($cur->getElementsByTagName('value'))->item(0);
    $convert = $context->convert_method($target->nodeValue);
    $context->write( ($convert) ? $convert : $target->nodeValue);
    return false;
  }
}

class ActualParamListProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->write( "(");
    return false;
  }
  function post_process($cur, $context, $contextData) {
    $context->write( ")");
  }
}
class ActualParamProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    if ($cur->previousSibling->nodeName == 'AST:Actual_parameter') {
      $context->write( ', ');
    }
  }
}

class FormalParamListProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->write( "(");
    return false;
  }
  function post_process($cur, $context, $contextData) {
      $context->write(")");
  }
}

class FormalParamProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    if ($cur->previousSibling->nodeName == 'AST:Formal_parameter') {
      $context->write(', ');
    }
  }
}

class TryProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->new_line("try:", 1);
    $stats     = $cur->firstChild->nextSibling;
    $catchList = $stats->nextSibling;
    $context->process_down($stats);
    $context->new_line("", -1);
    $context->process_down($catchList);
    return true;
  }
  function post_process($cur, $context, $contextData) {
  }
}

class CatchProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->write('except ');
    $class = $cur->firstChild->nextSibling;
    $var   = $class->nextSibling;
    $stats = $var->nextSibling;
    $context->process_down($class);
    $context->write(', ');
    $context->process_down($var);
    $context->new_line(":", 1);
    $context->process_down($stats);
    return true;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line("", -1);
  }
}

class ThrowProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
    $context->write('raise ');
    return false;
  }
  function post_process($cur, $context, $contextData) {
    $context->new_line();
  }
}

class ConditionalExprProcessor extends NodeProcessor {
  function pre_process($cur, $context, $contextData) {
	$cond  = $cur->firstChild->nextSibling;
	$true  = $cond->nextSibling;
	$false = $true->nextSibling;

	$context->process_down($true);
	$context->write(" if ");
	$context->process_down($cond);
	$context->write(" else ");
	$context->process_down($false);
	return true;
  }
}

class ProcessContext {
  private $indent = 0;
  private $indent_unit = "  ";
  private $context_type;
  private $method_map;
  private $result = "";
  
  private $dom;
  private $dispatcher;
  private $defaultProcessor;
  
  function __construct($file) {
    $this->dispatcher = array(
          'AST:PHP_script'            => new NodeProcessor(),
          'AST:Statement_list'        => new NodeProcessor(),
          'AST:Eval_expr'             => new ExprProcessor(),
          'AST:CONSTANT_NAME'         => new ValueProcessor(),
          'AST:BOOL'                  => new ValueProcessor(),
          'AST:INT'                   => new IntProcessor(),
          'AST:REAL'                  => new RealProcessor(),
          'AST:STRING'                => new StringProcessor(),
          'AST:Variable'              => new VariableProcessor(),
          'AST:VARIABLE_NAME'         => new VariableNameProcessor(),
          'AST:Array'                 => new ArrayProcessor(),
          'AST:Array_elem'            => new ArrayElemProcessor(),
          'AST:Assignment'            => new AssignmentProcessor(),
		  'AST:Op_assignment'         => new OpAssignmentProcessor(),
          'AST:OP'                    => new OpProcessor(),
          'AST:Pre_op'                => new PreOpProcessor(),
          'AST:Class_def'             => new ClassDefProcessor(),
          'AST:CLASS_NAME'            => new ClassNameProcessor(),
          'AST:Attribute'             => new AttributeProcessor(),
          'AST:Method_invocation'     => new MethodInvocationProcessor(),
          'AST:Method'                => new MethodProcessor(),
          'AST:Signature'             => new SignatureProcessor(),
          'AST:METHOD_NAME'           => new MethodNameProcessor(),
          'AST:Actual_parameter_list' => new ActualParamListProcessor(),
          'AST:Actual_parameter'      => new ActualParamProcessor(),
          'AST:Formal_parameter_list' => new FormalParamListProcessor(),
          'AST:Formal_parameter'      => new FormalParamProcessor(),
          'AST:For'                   => new ForProcessor(),
          'AST:Foreach'               => new ForeachProcessor(),
          'AST:If'                    => new IfProcessor(),
          'AST:Switch'                => new SwitchProcessor(),
          'AST:While'                 => new WhileProcessor(),
          'AST:Do'                    => new DoProcessor(),
          'AST:Return'                => new ReturnProcessor(),
          'AST:Try'                   => new TryProcessor(),
          'AST:Catch'                 => new CatchProcessor(),
          'AST:Throw'                 => new ThrowProcessor(),
		  'AST:Conditional_expr'      => new ConditionalExprProcessor(),
    );
    $this->defaultProcessor = new NodeProcessor();
    $this->method_map = array(
          'echo'         => 'print',
          '__construct'  => '__init__'
    );
    
    $dom = new DomDocument();
    $dom->preserveWhiteSpace = false;
    $dom->load($file);
    $this->dom = $dom;
  }

  public function start_process() {
    $this->process_node($this->dom->firstChild);
  }
  
  function process_node($cur, $contextData=null) {
    if (!$cur->nodeName) { return; }
    $this->process_down($cur, $contextData);
    $this->process_node($cur->nextSibling, $contextData);
  }

  function process_down($cur, $contextData=null) {
    $processor = $this->dispatcher[$cur->nodeName];
    if (!$processor) { $processor = $this->defaultProcessor; }
    $processor->comment_process($cur, $this, $contextData);
    $done = $processor->pre_process($cur, $this, $contextData);
    if (!$done) {
      $this->process_node($cur->firstChild, $contextData);
    }
    $processor->post_process($cur, $this, $contextData);
  }

  function inc_indent() { $this->indent++; }
  function dec_indent() { $this->indent--; }
  function print_indent() {
    $indent = str_mul($this->indent_unit, $this->indent);
    $this->result .= $indent;
  }
  function new_line($break_text="", $indent_delta=0, $force_new_line=false) {
    $this->write("$break_text\n", $force_new_line);
    $this->indent += $indent_delta;
  }
  function write($code, $force_new_line=false) {
    if ($this->result[strlen($this->result) - 1] == "\n") {
      if (strlen($code) > 0 && $code != "\n") {
        $this->print_indent();
        $this->result .= $code;
      }
      else if ($code == "\n" && $force_new_line) {
        $this->result .= $code;
      }
    }
    else {
      $this->result .= $code;
    }
  }
  function get_result($with_script_path=false) {
	$this->result = "#-*- coding: utf-8 -*-\n" . $this->result;	
	if ($with_script_path) {
	  $path = `which python`;
	  $this->result = "#!" . $path . $this->result;
	}

    return $this->result;
  }

  function convert_method($name) {
    return $this->method_map[$name];
  }
  function add_method_map($array) {
    $this->method_map = $this->method_map + $array;
  }
}



# __main__
if (count($argv) != 2)      die("Usage: php2py.php input_file\n");
if (!file_exists($argv[1])) die("file : {$argv[1]} is not found\n");

$context = new ProcessContext($argv[1]);
$context->start_process();
print $context->get_result(true);
