<?php

namespace App\Application\Controllers;

use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class GraphQLController
{
    protected $db;
    protected $logger;
    
    
    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->db = $connection;
        $this->logger = $logger;
    }

    public function index(Request $request, Response $response) {
        $this->setResolvers(include dirname(__DIR__, 3) . '/src/GraphQL/resolvers.php');
        $schema = \GraphQL\Utils\BuildSchema::build(file_get_contents(dirname(__DIR__, 3) . '/src/GraphQL/schema.graphqls'));
        
        $input = $request->getParsedBody();
        $query = $input['query'];

        $variables = isset($input['variables']) ? $input['variables'] : null;

        $context = [
            'db'      => $this->db,
            'logger'  => $this->logger
        ];

        $result = \GraphQL\GraphQL::executeQuery($schema, $query, null, $context, $variables);

        # This line is not needed if you use StandardServer
        $response->getBody()->write(json_encode($result));

        $sqlQueryLogger = $this->db->getConfiguration()->getSQLLogger();
        $this->logger->info(json_encode($sqlQueryLogger->queries));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function setResolvers($resolvers){
        
        \GraphQL\Executor\Executor::setDefaultFieldResolver(function ($source, $args, $context, \GraphQL\Type\Definition\ResolveInfo $info) use ($resolvers) {
        $fieldName = $info->fieldName;

            if (is_null($fieldName)) {
                throw new \Exception('Could not get $fieldName from ResolveInfo');
            }

            if (is_null($info->parentType)) {
                throw new \Exception('Could not get $parentType from ResolveInfo');
            }

            $parentTypeName = $info->parentType->name;

            if (isset($resolvers[$parentTypeName])) {
                $resolver = $resolvers[$parentTypeName];

                if (is_array($resolver)) {
                    if (array_key_exists($fieldName, $resolver)) {
                        $value = $resolver[$fieldName];

                        return is_callable($value) ? $value($source, $args, $context, $info) : $value;
                    }
                }

                if (is_object($resolver)) {
                    if (isset($resolver->{$fieldName})) {
                        $value = $resolver->{$fieldName};

                        return is_callable($value) ? $value($source, $args, $context, $info) : $value;
                    }
                }
            }

            return \GraphQL\Executor\Executor::defaultFieldResolver($source, $args, $context, $info);
        });
    }

    public function call_curl($url){

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, True);
        $return = curl_exec($curl);
        curl_close($curl);

        return json_decode($return, true);

    }

    private function getCharacteres(){

        $url_characteres = 'https://thronesapi.com/api/v2/Characters';

        return array_column(call_curl($url_characteres), 'imageUrl', 'fullName');

    }

    private function getQuotes(){

        $url_quotes = 'https://api.gameofthronesquotes.xyz/v1/characters';

        return array_column(call_curl($url_quotes), 'name', 'quotes');

    }

}