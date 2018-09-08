<?php
// Test this using following command
// php -S localhost:8080 ./graphql.php &
// curl http://localhost:8080 -d '{"query": "query { echo(message: \"Hello World\") }" }'
// curl http://localhost:8080 -d '{"query": "mutation { sum(x: 2, y: 2) }" }'
require_once __DIR__ . '/./vendor/autoload.php';
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;

//https://www.weichieprojects.com/blog/curl-api-calls-with-php/
function callAPI($method, $url, $data){
   $curl = curl_init();

   switch ($method){
      case "POST":
         curl_setopt($curl, CURLOPT_POST, 1);
         if ($data)
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
         break;
      case "PUT":
         curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
         if ($data)
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
         break;
      default:
         if ($data)
            $url = sprintf("%s?%s", $url, http_build_query($data));
   }

   // OPTIONS:
   curl_setopt($curl, CURLOPT_URL, $url);
   curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'APIKEY: 111111111111111111111',
      'Content-Type: application/json',
   ));
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

   // EXECUTE:
   $result = curl_exec($curl);
   if(!$result){die("Connection Failure");}
   curl_close($curl);
   //return json_decode($result, true);
   $obj = json_decode($result);
   
   if(empty($obj->records)){
		$final = $obj;
   }else{
		$final = $obj->records;
   }
   
   return $final;
}

try {
    $queryType = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'echo' => [
                'type' => Type::string(),
                'args' => [
                    'message' => ['type' => Type::string()],
                ],
                'resolve' => function ($root, $args) {
                    return $root['prefix'] . $args['message'];
                }
            ],
        ],
    ]);
	
	$RegionType = new ObjectType([
        'name' => 'RegionType',
        'fields' => [
            'id_region' => [ 'type' => Type::int() ],
			'nombre' => [ 'type' => Type::string() ]
        ],
    ]);
	
	$UserType = new ObjectType([
        'name' => 'UserType',
        'fields' => [
            'id_usuario' => [ 'type' => Type::int() ],
			'idRegion' => [ 'type' => Type::int() ],
			'usr_nombre' => [ 'type' => Type::string() ],			
			'isAdmin' => [ 'type' => Type::boolean() ],
			'region' => [ 'type' => $RegionType,			
						  'resolve' => function ($root, $args) {								
								return callAPI('GET', 'http://localhost:9080/api/api.php/records/cregiones/' . $root->idRegion, false);
							}
						]
        ],
    ]);
	
	$RootQuery = new ObjectType([
        'name' => 'RootQueryType',
        'fields' => [
			'region' => [
					'type' => $RegionType,
					'args' => [
						'id' => ['type' => Type::int()],
					],
					'resolve' => function ($root, $args) {
						return callAPI('GET', 'http://localhost:9080/api/api.php/records/cregiones/' . $args['id'], false);
					}
				],
            'user' => [
                'type' => $UserType,
                'args' => [
                    'id' => ['type' => Type::int()],
                ],
                'resolve' => function ($root, $args) {
                    return callAPI('GET', 'http://localhost:9080/api/api.php/records/tusuarios/' . $args['id'], false);
                }
            ],
			'users' => [
                'type' => new ListOfType($UserType),
                'resolve' => function ($root, $args) {
                    return callAPI('GET', 'http://localhost:9080/api/api.php/records/tusuarios/', false);
                }
            ]
        ],
    ]);
	
    $mutationType = new ObjectType([
        'name' => 'Calc',
        'fields' => [
            'sum' => [
                'type' => Type::int(),
                'args' => [
                    'x' => ['type' => Type::int()],
                    'y' => ['type' => Type::int()],
                ],
                'resolve' => function ($root, $args) {
                    return $args['x'] + $args['y'];
                },
            ],
        ],
    ]);
    // See docs on schema options:
    // http://webonyx.github.io/graphql-php/type-system/schema/#configuration-options
    $schema = new Schema([
        'query' => $RootQuery,
        'mutation' => $mutationType,
    ]);
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $query = $input['query'];
    $variableValues = isset($input['variables']) ? $input['variables'] : null;
    $rootValue = ['prefix' => 'You said: '];
    $result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = [
        'error' => [
            'message' => $e->getMessage()
        ]
    ];
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($output);