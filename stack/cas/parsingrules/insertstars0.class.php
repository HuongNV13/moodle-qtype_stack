<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.
defined('MOODLE_INTERNAL')|| die();
require_once(__DIR__ . '/baselogic.class.php');
require_once(__DIR__ . '/../../maximaparser/MP_classes.php');

// This class is the base of the old insert stars logics 0-5
class stack_parser_logic_insertstars0 extends stack_parser_logic {
    // These control the logic, if these are false the logic will tag
    // things as invalid if it meets core syntax rules matching these.
    private $insertstars = false;
    private $fixspaces = false;

    public function __construct($insertstars = false, $fixspaces = false) {
        $this->insertstars = $insertstars;
        $this->fixspaces = $fixspaces;
    }

    public function parse(&$string, &$valid, &$errors, &$answernote, $syntax, $safevars, $safefunctions) {
        $ast = $this->preparse($string, $valid, $errors, $answernote, $this->insertstars, $this->fixspaces);
        // If the parser fails it has already markeed all the correct errors.
        if($ast === null) {
            return null;
        }
        // Fix the common magic markkers.
        $this->commonpostparse($ast);
        // Give a place to hook things in.
        $this->pre($ast, $valid, $errors, $answernote, $syntax, $safevars, $safefunctions);
        // The common insertstars rules.
        $this->handletree($ast, $valid, $errors, $answernote, $syntax, $safevars, $safefunctions);
        // Give a place to hook things in.
        $this->post($ast, $valid, $errors, $answernote, $syntax, $safevars, $safefunctions);

        // Common stars insertion error.
        if (!$valid && array_search('missing_stars', $answernote) !== false) {
          $hasany = false;
          $check = function($node)  use(&$hasany) {
            if ($node instanceof MP_Operation && $node->op === '*' && $node->position === null) {
              $hasany = true;
            }
            return true;
          };
          $ast->callbackRecurse($check);
          if ($hasany) {
            // As we ouput the AST as a whole including the MP_Root there will be extra chars at the end.
            $missingstring = core_text::substr(stack_utils::logic_nouns_sort($ast->toString(array('red_null_position_stars' => true)), 'remove'), 0, -2);
            $a['cmd']  = stack_maxima_format_casstring(str_replace('QMCHAR', '?', $missingstring));
            $errors[] = stack_string('stackCas_MissingStars', $a);
          }
        }

        // Common spaces insertion errors.
        if (!$valid && array_search('spaces', $answernote) !== false) {
          $hasany = false;
          $checks = function($node)  use(&$hasany) {
            if ($node instanceof MP_Operation && $node->op === '*' && $node->position === false) {
              $hasany = true;
            }
            return true;
          };
          $ast->callbackRecurse($checks);
          if ($hasany) {
            $missingstring = core_text::substr(stack_utils::logic_nouns_sort($ast->toString(array('red_false_position_stars_as_spaces' => true)), 'remove'), 0, -2);
            $a['expr']  = stack_maxima_format_casstring(str_replace('QMCHAR', '?', $missingstring));
            $errors[] = stack_string('stackCas_spaces', $a);
          }
        }

        return $ast;
    }

    // Here is a hook to place things if you want to extend.
    public function pre($ast, &$valid, &$errors, &$answernote, $syntax, $safevars, $safefunctions) {
        return;
    }

    // Here is a hook to place things if you want to extend.
    public function post($ast, &$valid, &$errors, &$answernote, $syntax, $safevars, $safefunctions) {
        return;
    }

    // This applies all the common old rules about suitable names and
    // numbers mixed in them. Also certain other old tricks.
    private function handletree($ast, &$valid, &$errors, &$answernote, $syntax, $safevars, $safefunctions) {

        $process = function($node) use (&$valid, $errors, &$answernote, $syntax, $safevars, $safefunctions) {
            if ($node instanceof MP_FunctionCall) {
                // Do not touch functions with names that are safe.
                if(($node->name instanceof MP_Identifier || $node->name instanceof MP_String)&& isset($safefunctions[$node->name->value])) {
                    return true;
                }
                // Skip the very special identifiers for log-candy.
                if(($node->name instanceof MP_Identifier || $node->name instanceof MP_String)&&($node->name->value === 'log10' || core_text::substr($node->name->value, 0, 4)=== 'log_')) {
                    return true;
                }
                // a(x)(y) => a(x)*(y) or (x)(y) => (x)*(y)
                if($node->name instanceof MP_FunctionCall || $node->name instanceof MP_Group) {
                    $answernote[] = 'missing_stars';
                    if(!$this->insertstars) {
                        $valid = false;
                    }
                    $newop = new MP_Operation('*', $node->name, new MP_Group($node->arguments));
                    $node->parentnode->replace($node, $newop);
                    return false;
                }
                if($node->name instanceof MP_Identifier) {
                    // students may not have functionnames ending with numbers...
                    if(ctype_digit(core_text::substr($node->name->value, -1))) {
                        $replacement = new MP_Operation('*', $node->name, new MP_Group($node->arguments));
                        $answernote[] = 'missing_stars';
                        if(!$this->insertstars) {
                            $valid = false;
                        }
                        $node->parentnode->replace($node, $replacement);
                        return false;
                    } else if($node->name->value === 'i') {
                        $replacement = new MP_Operation('*', $node->name, new MP_Group($node->arguments));
                        $answernote[] = 'missing_stars';
                        if(!$this->insertstars) {
                            $valid = false;
                        }
                        $node->parentnode->replace($node, $replacement);
                        return false;
                    } else if(!$syntax &&(core_text::strlen($node->name->value)== 1)) {
                        // single character function names... TODO: what is this!?
                        $replacement = new MP_Operation('*', $node->name, new MP_Group($node->arguments));
                        $answernote[] = 'missing_stars';
                        if(!$this->insertstars) {
                            $valid = false;
                        }
                        $node->parentnode->replace($node, $replacement);
                        return false;
                    }
                }
            } else if($node instanceof MP_Identifier && !($node->parentnode instanceof MP_FunctionCall)) {
                // Do not touch variables that are safe. e.g. unit names.
                if(isset($safevars[$node->value])) {
                    return true;
                }
                // Skip the very special identifiers for log-candy. These will be reconstructed
                // as fucntion calls elsewhere.
                if($node->value === 'log10' || core_text::substr($node->value, 0, 4)=== 'log_') {
                    return true;
                }
                // x3 => x*3, we could handle the 2-char case in the latter ones too...
                if(!$syntax && core_text::strlen($node->value)=== 2 && ctype_alpha(core_text::substr($node->value, 0, 1))&& ctype_digit(core_text::substr($node->value, 1, 1))) {
                    // Binding powers will be wrong but we are not evaluating stuff here.
                    $replacement = new MP_Operation('*', new MP_Identifier(core_text::substr($node->value, 0, 1)), new MP_Integer((int) core_text::substr($node->value, 1, 1)));
                    $answernote[] = 'missing_stars';
                    if(!$this->insertstars) {
                        $valid = false;
                    }
                    $node->parentnode->replace($node, $replacement);
                    return false;
                }
            }
            if ($node instanceof MP_Identifier) {
                // Check for a1b2c => a1*b2*c, i.e. shifts from number to letter in the name.
                $splits = array();
                $alpha = true;
                $last = 0;
                for($i = 1; $i < core_text::strlen($node->value); $i++) {
                    if($alpha && ctype_digit(core_text::substr($node->value, $i, 1))) {
                        $alpha = false;
                    } else if(!$alpha && !ctype_digit(core_text::substr($node->value, $i, 1))) {
                        $alpha = false;
                        $splits[] = core_text::substr($node->value, $last, $i - $last);
                        $last = $i;
                    }
                }
                $splits[] = core_text::substr($node->value, $last);
                if(count($splits)> 1) {
                    $answernote[] = 'missing_stars';
                    if(!$this->insertstars) {
                        $valid = false;
                    }
                    // Initial bit is turned to multiplication chain. The last one need to check for function call.
                    $temp = new MP_Identifier('rhs');
                    $replacement = new MP_Operation('*', new MP_Identifier($splits[0]), $temp);
                    $iter = $replacement;
                    $i = 1;
                    for($i = 1; $i < count($splits) - 1;$i++) {
                        $iter->replace($temp, new MP_Operation('*', new MP_Identifier($splits[$i]), $temp));
                        $iter = $iter->rhs;
                    }
                    if($node->is_function_name()) {
                        $iter->replace($temp, new MP_FunctionCall(new MP_Identifier($splits[$i]), $node->parentnode->arguments));
                        $node->parentnode->parentnode->replace($node->parentnode, $replacement);
                    } else {
                        $iter->replace($temp, new MP_Identifier($splits[$i]));
                        $node->parentnode->replace($node, $replacement);
                    }
                    return false;
                }
                // xyz12 => xyz*12
                if(!$syntax && ctype_digit(core_text::substr($node->value, -1))) {
                    $i = 0;
                    for($i = 0; $i < core_text::strlen($node->value); $i++) {
                        if(ctype_digit(core_text::substr($node->value, $i, 1))) {
                            break;
                        }
                    }
                    // Note at this point i.e. after the "a1b2c" the split should be clean and the remainder is just an integer.
                    $replacement = new MP_Operation('*', new MP_Identifier(core_text::substr($node->value, 0, $i)), new MP_Integer((int) core_text::substr($node->value, $i)));
                    if($node->parentnode instanceof MP_FunctionCall && $node->parentnode->name === $node) {
                        $replacement->rhs = new MP_Operation('*', $replacement->rhs, new MP_Group($node->parentnode->arguments));
                        $node->parentnode->parentnode->replace($node->parentnode, $replacement);
                    } else {
                        $node->parentnode->replace($node, $replacement);
                    }
                    $answernote[] = 'missing_stars';
                    if(!$this->insertstars) {
                        $valid = false;
                    }
                    return false;
                }
            }
            if (!$syntax && $node instanceof MP_Float && $node->raw !== null) {
                // TODO: When and how does this need to break the floats?
                // This is one odd case to handle but maybe some people want to kill floats like this.
                $replacement = false;
                if (strpos($node->raw, 'e') !== false) {
                    $parts = explode('e', $node->raw);
                    if (strpos($parts[0], '.') !== false) {
                        $replacement = new MP_Operation('*', new MP_Float(floatval($parts[0]), null), new MP_Operation('*', new MP_Identifier('e'), new MP_Integer(intval($parts[1]))));
                    } else {
                        $replacement = new MP_Operation('*', new MP_Integer(intval($parts[0])), new MP_Operation('*', new MP_Identifier('e'), new MP_Integer(intval($parts[1]))));
                    }
                    if ($parts[1]{0} === '-' || $parts[1]{0} === '+') {
                        // 1e+1...
                        $op = $parts[1]{0};
                        $val = abs(intval($parts[1]));
                        $replacement = new MP_Operation($op, new MP_Operation('*', $replacement->lhs, new MP_Identifier('e')), new MP_Integer($val));
                    }
                } else if (strpos($node->raw, 'E') !== false) {
                    $parts = explode('E', $node->raw);
                    if (strpos($parts[0], '.') !== false) {
                        $replacement = new MP_Operation('*', new MP_Float(floatval($parts[0]), null), new MP_Operation('*', new MP_Identifier('E'), new MP_Integer(intval($parts[1]))));
                    } else {
                        $replacement = new MP_Operation('*', new MP_Integer(intval($parts[0])), new MP_Operation('*', new MP_Identifier('E'), new MP_Integer(intval($parts[1]))));
                    }
                    if ($parts[1]{0} === '-' || $parts[1]{0} === '+') {
                        // 1.2E-1...
                        $op = $parts[1]{0};
                        $val = abs(intval($parts[1]));
                        $replacement = new MP_Operation($op, new MP_Operation('*', $replacement->lhs, new MP_Identifier('E')), new MP_Integer($val));
                    }
                }
                if ($replacement !== false) {
                    $answernote[] = 'missing_stars';
                    if(!$this->insertstars) {
                        $valid = false;
                    }
                    $node->parentnode->replace($node, $replacement);
                    return false;
                }
            }
            return true;
        };

        while ($ast->callbackRecurse($process) !== true) {}
    }
}