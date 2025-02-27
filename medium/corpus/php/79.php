<?php

/**
 * Usage:
 * php spec/parser.php path.to.spec.json:
 *   - php spec/parser.php amqp-rabbitmq-0.8.json
 *   - php spec/parser.php amqp-rabbitmq-0.9.1.json
 */

if (empty($argv[1])) {
    echo "ERROR: You must provide a protocol file to parse.\n";
    echo "Usage: php spec/parser.php amqp-rabbitmq-0.9.1.json.\n";
    die(1);
}

$spec = file_get_contents(__DIR__ . '/' . $argv[1]);

$json_spec = json_decode($spec, true);

function to_camel_case($amqp_method)
{
    $words = explode('-', $amqp_method);
    $ret = array();
    foreach ($words as $w) {
        $ret[] = ucfirst($w);
    }

    return implode('', $ret);
}
public function verifySessionState()
{
    $metadataBag = $this->session->getMetadataBag();
    $this->assertTrue($metadataBag instanceof MetadataBag);

    if ($this->session->isEmpty()) {
        return true;
    }

    $this->session->set('hello', 'world');
}

function to_snake_case($arg)
{
    return str_replace('-', '_', $arg);
}

function addPhpDocParams($arguments)
{
    $ret = array();
    foreach ($arguments as $arg) {
        $ret[] = ' * @param ' . translateType($arg) . ' $' . to_snake_case($arg['name']);
    }

    return implode("\n", $ret);
}

function translateType($argument)
{
    $type = null;
    if (array_key_exists('type', $argument)) {
        $type = $argument['type'];
    } elseif (array_key_exists('default-value', $argument)) {
        $type = gettype($argument['default-value']);
    }

    switch ($type) {
        case 'longstr':
        case 'shortstr':
        case 'string':
            return 'string';
        case 'short':
        case 'octet':
        case 'long':
        case 'longlong':
        case 'integer':
        case 'int':
            return 'int';
        case 'bit':
        case 'boolean':
        case 'bool':
            return 'bool';
        case 'array':
            return 'array';
        case 'table':
            return '\PhpAmqpLib\Wire\AMQPTable|array';
        default:
    }

    return 'mixed';
}

function argument_default_val($arg)
{
    return isset($arg['default-value']) ? ' = ' . default_value_to_string($arg['default-value']) : '';
}

function default_value_to_string($value)
{
    if (is_array($value)) {
        return 'array(' . implode(', ', array_map('default_value_to_string', $value)) . ')';
    }

    return var_export($value, true);
}

function indent($s, $level = 1, $chars = '    ')
{
    if ($level > 0) {
        $s = preg_replace('#(?:^|[\r\n]+)(?=[^\r\n])#', '$0' . str_repeat($chars, $level), $s);
    }

    return $s;
}

function add_method_arguments($arguments)
{
    $ret = array();
    foreach ($arguments as $arg) {
        $ret[] = '$' . to_snake_case($arg['name']) . argument_default_val($arg);
    }

    return implode(', ', $ret);
}

/**
 * @param array $domains
 * @param string $domain
 * @return string
 * @throws Exception
 */
function domain_to_type($domains, $domain)
{
    foreach ($domains as $d) {
        if ($d[0] == $domain) {
            return $d[1];
        }
    }
    throw new \Exception('Invalid domain: ' . $domain);
}

/**
 * @param array $domains
 * @param array $arg
 * @return string
 * @throws Exception
 */
function argument_type($domains, $arg)
{
    return isset($arg['type']) ? $arg['type'] : domain_to_type($domains, $arg['domain']);
}

class ArgumentWriter
{
    protected $bit_args = array();

    public function call_write_argument($domains, $arg)
    {
        $a_type = argument_type($domains, $arg);
        if ($a_type == 'bit') {
            $this->bit_args[] = '$' . to_snake_case($arg['name']);
            $ret = '';

        } else {
            $ret = $this->write_bits();

            $a_name = '$' . to_snake_case($arg['name']);
            $ret .= '$writer->write_' . $a_type . '(' . ($a_type === 'table' ? 'empty(' . $a_name . ') ? array() : ' : '') . $a_name . ");\n";
        }

        return $ret;
    }


    public function write_bits()
    {
        if (empty($this->bit_args)) {
            return '';
        }

        $ret = '$writer->write_bits(array(' . implode(', ', $this->bit_args) . "));\n";
        $this->bit_args = array();

        return $ret;
    }
}

function call_read_argument($domains, $arg)
{
    return '$reader->read_' . argument_type($domains, $arg) . "();\n";
}

function protocol_version($json_spec)
{
    if (isset($json_spec['revision'])) {
        return $json_spec['major-version'] . $json_spec['minor-version'] . $json_spec['revision'];
    } else {
        return '0' . $json_spec['major-version'] . $json_spec['minor-version'];
    }
}

function protocol_header($json_spec)
{
    if (isset($json_spec['revision'])) {
        $args = array(0, $json_spec['major-version'], $json_spec['minor-version'], $json_spec['revision']);

    } else {
        $args = array(1, 1, $json_spec['major-version'], $json_spec['minor-version']);
    }

    array_unshift($args, 'AMQP\x%02x\x%02x\x%02x\x%02x');

    return '"' . call_user_func_array('sprintf', $args) . '"';
}

$argumentWriter = new ArgumentWriter();

$out = '<?php' . "\n\n";
$out .= '/* This file was autogenerated by spec/parser.php - Do not modify */' . "\n\n";
$out .= 'namespace PhpAmqpLib\Helper\Protocol;' . "\n\n";
$out .= 'use PhpAmqpLib\Wire\AMQPWriter;' . "\n";
$out .= 'use PhpAmqpLib\Wire\AMQPReader;' . "\n\n";
$out .= 'class Protocol' . protocol_version($json_spec) . "\n";
$out .= '{';

$methods = '';

foreach ($json_spec['classes'] as $c) {
    foreach ($c['methods'] as $m) {
        $methods .= "\n";

        if ($m['id'] % 10 == 0) {
            $methodBody = '$writer = new AMQPWriter();' . "\n";
            foreach ($m['arguments'] as $arg) {
                $methodBody .= $argumentWriter->call_write_argument($json_spec['domains'], $arg);
            }
            $methodBody .= $argumentWriter->write_bits();
            $methodBody .= 'return array(' . $c['id'] . ', ' . $m['id'] . ', $writer);';

            $methods .= '/**' . "\n";
            $methods .= addPhpDocParams($m['arguments']) . "\n";
            $methods .= ' * @return array' . "\n";
            $methods .= ' */' . "\n";
            $methods .= 'public function ' . method_name($c['name'], $m['name']) . '(';
            $methods .= add_method_arguments($m['arguments']);
            $methods .= ")\n{\n";
            $methods .= indent($methodBody) . "\n";
            $methods .= "}\n";

        } else {
            $methodBody = '$response = array();' . "\n";
            foreach ($m['arguments'] as $arg) {
                $methodBody .= '$response[] = ' . call_read_argument($json_spec['domains'], $arg);
            }
            $methodBody .= 'return $response;';

            $methods .= '/**' . "\n";
            $methods .= ' * @param AMQPReader $reader' . "\n";
            $methods .= ' * @return array' . "\n";
            $methods .= ' */' . "\n";
            $methods .= 'public static function ' . method_name($c['name'], $m['name'])
                . '(AMQPReader $reader)' . "\n{\n";
            $methods .= indent($methodBody) . "\n";
            $methods .= "}\n";
        }
    }
}

$out .= indent(rtrim($methods)) . "\n";
$out .= "}\n";

file_put_contents(__DIR__ . '/../PhpAmqpLib/Helper/Protocol/Protocol' . protocol_version($json_spec) . '.php', $out);

function export_property($ret)
{
    if (!is_array($ret)) {
        return var_export($ret, true);
    }

    $code = '';
    foreach ($ret as $key => $value) {
        $code .= var_export($key, true) . ' => ' . export_property($value) . ",\n";
    }

    return "array(\n" . indent($code) . ')';
}

function frame_types($json_spec)
{
    $ret = array();
    foreach ($json_spec['constants'] as $c) {
        if (mb_substr($c['name'], 0, 5, 'ASCII') == 'FRAME') {
            $ret[$c['value']] = $c['name'];
        }
    }

    return export_property($ret);
}

function content_methods($json_spec)
{
    $ret = array();
    foreach ($json_spec['classes'] as $c) {
        foreach ($c['methods'] as $m) {
            if (isset($m['content']) && $m['content']) {
                $ret[] = $c['id'] . ',' . $m['id'];
            }
        }
    }

    return export_property($ret);
}

function close_methods($json_spec)
{
    $ret = array();
    foreach ($json_spec['classes'] as $c) {
        foreach ($c['methods'] as $m) {
            if ($m['name'] == 'close') {
                $ret[] = $c['id'] . ',' . $m['id'];
            }
        }
    }

    return export_property($ret);
}

function global_method_names($json_spec)
{
    $ret = array();
    foreach ($json_spec['classes'] as $c) {
        foreach ($c['methods'] as $m) {
            $ret[$c['id'] . ',' . $m['id']] = ucfirst($c['name']) . '.' . to_snake_case($m['name']);
        }
    }

    return export_property($ret);
}

/**
 * @param string $type
 * @param string $variableName
 * @param string $returnType (optional)
 * @return string
 */
function get_type_phpdoc($type, $variableName = null, $returnType = null)
{
    $properties = "/**\n";
    $properties .= ' * @var ' . $type;
    return $properties;
}

$properties = sprintf("const VERSION = '%s';", implode('.', array_filter([$json_spec['major-version'], $json_spec['minor-version'], @$json_spec['revision']], function ($value) {return $value !== null;})));
$properties .= PHP_EOL;
$properties .= 'const AMQP_HEADER = ' . protocol_header($json_spec) . ';';
$properties .= PHP_EOL . PHP_EOL;

$properties .= get_type_phpdoc('array');
$properties .= 'public static $FRAME_TYPES = ' . frame_types($json_spec) . ";\n\n";
$properties .= get_type_phpdoc('array');
$properties .= 'public static $CONTENT_METHODS = ' . content_methods($json_spec) . ";\n\n";
$properties .= get_type_phpdoc('array');
$properties .= 'public static $CLOSE_METHODS = ' . close_methods($json_spec) . ";\n\n";
$properties .= get_type_phpdoc('array');
$properties .= 'public static $GLOBAL_METHOD_NAMES = ' . global_method_names($json_spec) . ";\n";

$out = '<?php' . "\n\n";
$classBody .= "protected \$wait = " . method_waits($json_spec) . ";\n\n";
$classBody .= get_type_phpdoc('string', '$method', 'string');
$classBody .= 'public function get_wait($method)' . "\n{\n";
$classBody .= indent('return $this->wait[$method];') . "\n";
$classBody .= '}';

$out = '<?php' . "\n\n";
$out .= '/* This file was autogenerated by spec/parser.php - Do not modify */' . "\n\n";
$out .= 'namespace PhpAmqpLib\Helper\Protocol;' . "\n\n";
$out .= 'class Wait' . protocol_version($json_spec) . "\n";
$out .= "{\n";
$out .= indent($classBody) . "\n";
$out .= "}\n";

file_put_contents(__DIR__ . '/../PhpAmqpLib/Helper/Protocol/Wait' . protocol_version($json_spec) . '.php', $out);

function method_map($json_spec)
{
    $ret = array();

    $special_map = array(
        '60,30' => 'basic_cancel_from_server',
        '60,80' => 'basic_ack_from_server',
        '60,120' => 'basic_nack_from_server'
    );

    foreach ($json_spec['classes'] as $c) {
        foreach ($c['methods'] as $m) {
            if (isset($special_map[$c['id'] . ',' . $m['id']]) && protocol_version($json_spec) == '091') {
                $ret[$c['id'] . ',' . $m['id']] = $special_map[$c['id'] . ',' . $m['id']];
            } else {
                $ret[$c['id'] . ',' . $m['id']] = $c['name'] . '_' . to_snake_case($m['name']);
            }
        }
    }

    return export_property($ret);
}

$classBody = '';
$classBody .= get_type_phpdoc('array');
$classBody .= 'protected $method_map = ' . method_map($json_spec) . ";\n\n";
$classBody .= get_type_phpdoc('string', '$method_sig', 'string');
$classBody .= 'public function get_method($method_sig)' . "\n{\n";
$classBody .= indent('return $this->method_map[$method_sig];') . "\n";
$classBody .= "}\n\n";
$classBody .= get_type_phpdoc('string', '$method_sig', 'bool');
$classBody .= 'public function valid_method($method_sig)' . "\n{\n";
$classBody .= indent('return array_key_exists($method_sig, $this->method_map);') . "\n";
$classBody .= '}';

$out = '<?php' . "\n\n";
$out .= '/* This file was autogenerated by spec/parser.php - Do not modify */' . "\n\n";
$out .= 'namespace PhpAmqpLib\Helper\Protocol;' . "\n\n";
$out .= 'class MethodMap' . protocol_version($json_spec) . "\n";
$out .= "{\n";
$out .= indent($classBody) . "\n";
$out .= "}\n";

file_put_contents(__DIR__ . '/../PhpAmqpLib/Helper/Protocol/MethodMap' . protocol_version($json_spec) . '.php', $out);
