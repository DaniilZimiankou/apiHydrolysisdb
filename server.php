<?php
//Require files connection and token
// include 'jwt.php';
// include 'funcionsBdD.php';

// Set CORS headers
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// $issuedAt = time();
// $expirationTime = $issuedAt + 3600;

// $database = new Database();

class Server
{
  public function serve()
  {
    require_once 'jwt.php';
    require_once 'funcionsBdD.php';
    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    $paths = explode('/', $uri);
    array_shift($paths); // Hack; get rid of initials empty string
    array_shift($paths);
    //licalhost/API/$token/$resource/$identificador
    // $token = array_shift($paths);
    $resource = array_shift($paths);
    $identificador = array_shift($paths);

    $issuedAt = time();
    $expirationTime = $issuedAt + 3600;
    $functionsBdD = new FunctionsBdD();

    if ($resource == 'Login') { //--Funciona OK
        if ($method == "POST") {
            $put = json_decode(file_get_contents('php://input'), true);
            $UserEmail = $put["UserEmail"];
            $passwd = $put["passwd"];
        
            $result = $functionsBdD->login($UserEmail, $passwd);
        
            if ($result == false) {
                http_response_code(417);
                echo json_encode(array("message" => "User not found"));
            } else if (count($result) == 2) {
                $usuariID = $result[0][0]["usuariID"];
                $rol = $result[1][0]["rol"];
        
                $payload = [
                    'iat' => $issuedAt,
                    'exp' => $expirationTime,
                    'iss' => $rol,
                    'data' => array(
                        'username' => $usuariID,
                    )
                ];
        
                $jwt = $jwtHandler->generateToken($payload);
        
                http_response_code(200);
                echo json_encode(array(
                    "message" => "Login successful",
                    "jwt" => $jwt
                ));
            } else {
                http_response_code(200);
                echo json_encode($result);
            }
        }
        else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }





    else if ($resource == 'Register') { //--Funciona OK
        if ($method == "POST") {
            $put = json_decode(file_get_contents('php://input'), true);
            $nom = $put["nom"];
            $cognoms = $put["cognoms"];
            $email = $put["email"];
            $passwd = $put["passwd"];

            $result = $functionsBdD->register($nom, $cognoms, $email, $passwd);

            if ($result) {
                http_response_code(200);
                echo json_encode(array("message" => "User registered successfully"));
            } else {
                http_response_code(417);
                echo json_encode(array("message" => "Registration failed"));
            }
        }
        else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    } 



    // Agafa les dades dels experiments (acabat i acceptat)
    else if ($resource == 'Experiments') {
        if ($method == "GET") {
            
            $experiments = $functionsBdD->getCompletedAcceptedExperiments();
            if ($experiments) {
                
                $detailedExperiments = [];
                foreach ($experiments as $experiment) {
                    $experimentID = $experiment['experimentID'];
                    $experimentDetails = $functionsBdD->getExperimentDetails($experimentID);
                    if ($experimentDetails) {
                        $detailedExperiments[] = $experimentDetails;
                    }
                }
    
                if (!empty($detailedExperiments)) {
                    http_response_code(200);
                    echo json_encode($detailedExperiments);
                } else {
                    http_response_code(404);
                    echo json_encode(array("message" => "No detailed experiments found"));
                }
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "No experiments found"));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }




    // Agafa el token pasat pel header decodifica i captura l'id + les dades pasades JSON pel endpoint
    // guarda dades en BdD + si hay chart guarda el chart
    if ($resource == 'CreateExperiment') {
        if ($method == 'POST') {
            try {

                $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
    
                if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                    $jwt = $matches[1];
    
                    if ($jwt) {

                        $decoded = $jwtHandler->decodeToken($jwt);
                        $usuariID = $decoded->data->username; 
    
                        
                        $put = json_decode(file_get_contents('php://input'), true);
    
                        
                        $requiredFields = ['nom_experiment', 'estat', 'comprovacio', 'catalitzador', 'ph', 'temps', 'temperatura', 'componentID', 'grams', 'concentracio'];
                        foreach ($requiredFields as $field) {
                            if (!isset($put[$field])) {
                                throw new Exception("Missing required field: $field");
                            }
                        }
    
                        
                        $experimentID = $functionsBdD->createExperiment(
                            $usuariID, $put['nom_experiment'], $put['estat'], $put['comprovacio'], $put['catalitzador'],
                            $put['ph'], $put['temps'], $put['temperatura'], $put['componentID'], $put['grams'], $put['concentracio']
                        );
    
                        if ($experimentID) {
                            // Check if chart data exists
                            if (isset($put['chartData']) && !empty($put['chartData'])) {
                                $chartData = $put['chartData'];
                                $chartTitle = $chartData['title'];
                                $chartPoints = $chartData['dates'];
    
                                // Save chart
                                $chartResult = $functionsBdD->saveChart($experimentID, $chartTitle, $chartPoints);
                                if ($chartResult) {
                                    http_response_code(201); // Created
                                    echo json_encode(array("message" => "Experiment and chart created successfully."));
                                } else {
                                    http_response_code(500); // Internal server Error
                                    echo json_encode(array("message" => "Experiment created but failed to save chart."));
                                }
                            } else {
                                http_response_code(201); // Created
                                echo json_encode(array("message" => "Experiment created successfully."));
                            }
                        } else {
                            http_response_code(500); // Internal server Error
                            echo json_encode(array("message" => "Failed to create experiment"));
                        }
                    } else {
                        http_response_code(401); // Unauthorized
                        echo json_encode(array("message" => "Unauthorized"));
                    }
                } else {
                    http_response_code(401); // Unauthorized
                    echo json_encode(array("message" => "Authorization header missing or invalid format"));
                }
            } catch (Exception $e) {
                http_response_code(500); // Internal server Error
                echo json_encode(array("message" => "Server error: " . $e->getMessage()));
            }
        } else {
            http_response_code(405); // Method not Allowed
            echo json_encode(array("message" => "Method not allowed"));
        }
    }
    



    // Fa l'ho mateix que abans amb token pero per poder selecionar el experiments del usuari concret
    else if ($resource == 'MyExperiments') { 
        if ($method == "GET") {

            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
            if ($authHeader) {

                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
    
                if ($decoded) {

                    $userID = $decoded->data->username;
                    

                    $experiments = $functionsBdD->getExperimentsByUser($userID);
                    
                    if ($experiments) {
                        $detailedExperiments = [];
                        
                        foreach ($experiments as $experiment) {
                            $experimentID = $experiment['experimentID'];
                            $experimentDetails = $functionsBdD->getExperimentDetails($experimentID);
                            
                            if ($experimentDetails) {
                                $detailedExperiments[] = $experimentDetails;
                            }
                        }
    
                        if (!empty($detailedExperiments)) {
                            http_response_code(200);
                            echo json_encode($detailedExperiments);
                        } else {
                            http_response_code(404);
                            echo json_encode(array("message" => "No detailed experiments found"));
                        }
                    } else {
                        http_response_code(404);
                        echo json_encode(array("message" => "No experiments found"));
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Invalid token"));
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Authorization header not found"));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }




    // Simple select from components
    else if ($resource == 'Components') { //--Funciona OK
        if ($method == "GET") {
          echo json_encode($functionsBdD->getComponents());
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }




    // Captura id del usuari des del token y hace update a la tabla experiment i si n'hi ha chart ho crea o modifica
    else if ($resource == 'UpdateExperiment') {
        if ($method == "POST") {
            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if ($authHeader) {
                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
                
                if ($decoded) {
                    $experimentData = json_decode(file_get_contents("php://input"), true);
                    if ($functionsBdD->updateExperiment($experimentData) && $functionsBdD->updateUtilitza($experimentData) 
                    && $functionsBdD->updateChart($experimentData)) {
                        echo json_encode(['status' => 'success']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['status' => 'error', 'message' => 'Failed to update experiment or utilitza.']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Token missing.']);
            }
        }
    }




    // Mira el rol d'usuari
    if ($resource == 'check-role') {
        if ($method == "GET") {
            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $jwt = $matches[1];
                $decoded = $jwtHandler->decodeToken($jwt);
                $role = $decoded->iss;
                echo json_encode(['role' => $role]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
            }
        }
    }




    // Endpoint per eliminar experiment (Mira si el que elimina experiment es el qui ho va crear o el admin)
    if ($resource == 'DeleteExperiment') {
        if ($method == "DELETE") {
            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

            if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $jwt = $matches[1];
                $decoded = $jwtHandler->decodeToken($jwt);

                if ($decoded) {
                    $usuariID = $decoded->data->username;
                    $role = $decoded->iss;
                    $experimentID = $identificador;


                    // Get experiment owner ID
                    $getExperimentOwnerID = $functionsBdD->getExperimentOwnerID($experimentID);
                    print_r($getExperimentOwnerID);
                    $experimentOwnerID = $getExperimentOwnerID['usuariID'];

                    // if ($experimentOwnerID === false) {
                    //     http_response_code(404);
                    //     echo json_encode(['error' => 'Experiment not found']);
                    //     exit();
                    // }

                    // Check if the user is the owner or an admin
                    if ($usuariID === $experimentOwnerID || $role === 'administrador') {
                        if ($functionsBdD->deleteExperiment($experimentID)) {
                            echo json_encode(['message' => 'Experiment deleted successfully']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['error' => 'Failed to delete experiment']);
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid token']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Authorization header not found']);
            }
        }
    }






    else if ($resource == 'AllExperiments') { 
        if ($method == "GET") {

            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
            if ($authHeader) {

                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
    
                if ($decoded) {

                    $usuariID = $decoded->data->username;
                    $role = $decoded->iss;
                    
                    if ($role === 'administrador'){
                        $experiments = $functionsBdD->getAllExperiments();
                    
                        if ($experiments) {
                            $detailedExperiments = [];
                        
                            foreach ($experiments as $experiment) {
                                $experimentID = $experiment['experimentID'];
                                $experimentDetails = $functionsBdD->getExperimentDetails($experimentID);
                            
                                if ($experimentDetails) {
                                $detailedExperiments[] = $experimentDetails;
                                }
                            }
    
                            if (!empty($detailedExperiments)) {
                                http_response_code(200);
                                echo json_encode($detailedExperiments);
                            } else {
                                http_response_code(404);
                                echo json_encode(array("message" => "No detailed experiments found"));
                            }
                        } else {
                            http_response_code(404);
                            echo json_encode(array("message" => "No experiments found"));
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Invalid token"));
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Authorization header not found"));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }





    else if ($resource == 'UpdateExperimentStatus') {
        if ($method == "POST") {
    
            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
            if ($authHeader) {
    
                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
    
                if ($decoded) {
    
                    $role = $decoded->iss;
                    
                    if ($role === 'administrador') {
                        $data = json_decode(file_get_contents('php://input'), true);
                        $experimentID = $data['experimentID'] ?? null;
                        $status = $data['status'] ?? null;
    
                        if ($experimentID && $status) {
                            $updateResult = $functionsBdD->updateExperimentStatus($experimentID, $status);
    
                            if ($updateResult) {
                                http_response_code(200);
                                echo json_encode(array("message" => "Experiment status updated successfully"));
                            } else {
                                http_response_code(500);
                                echo json_encode(array("message" => "Failed to update experiment status"));
                            }
                        } else {
                            http_response_code(400);
                            echo json_encode(array("message" => "Missing required fields"));
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(array("error" => "Unauthorized"));
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Invalid token"));
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Authorization header not found"));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }





    else if ($resource == 'PendentExperiments') { 
        if ($method == "GET") {

            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
            if ($authHeader) {

                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
    
                if ($decoded) {

                    $usuariID = $decoded->data->username;
                    $role = $decoded->iss;
                    
                    if ($role === 'administrador'){
                        $experiments = $functionsBdD->getPendentExperiments();
                        if ($experiments) {
                            
                            $detailedExperiments = [];
                            foreach ($experiments as $experiment) {
                                $experimentID = $experiment['experimentID'];
                                $experimentDetails = $functionsBdD->getExperimentDetails($experimentID);
                                if ($experimentDetails) {
                                    $detailedExperiments[] = $experimentDetails;
                                }
                            }
                
                            if (!empty($detailedExperiments)) {
                                http_response_code(200);
                                echo json_encode($detailedExperiments);
                            } else {
                                http_response_code(404);
                                echo json_encode(array("message" => "No detailed experiments found"));
                            }
                        } else {
                            http_response_code(404);
                            echo json_encode(array("message" => "No experiments found"));
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Invalid token"));
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Authorization header not found"));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }





    else if ($resource == 'AllUsers') { 
        if ($method == "GET") {

            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
            if ($authHeader) {

                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
    
                if ($decoded) {

                    $usuariID = $decoded->data->username;
                    $role = $decoded->iss;
                    
                    if ($role === 'administrador'){
                        $usuaris = $functionsBdD->getAllUsuaris($usuariID);
                        if ($usuaris) {
                                http_response_code(200);
                                echo json_encode($usuaris);
                        } else {
                            http_response_code(404);
                            echo json_encode(array("message" => "No experiments found"));
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Invalid token"));
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Authorization header not found"));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }





    else if ($resource == 'UpdateUsuari') {
        if ($method == "POST") {
            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if ($authHeader) {
                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
                
                if ($decoded) {
                    $usuariID = $decoded->data->username;
                    $role = $decoded->iss;
                    
                    if ($role === 'administrador'){
                        $usuariData = json_decode(file_get_contents("php://input"), true);
                        $usuariEmail = $usuariData['email'];
                        $usuariIDsended = $usuariData['usuariID'];
                        $emailExistent = $functionsBdD->comprovarEmail($usuariEmail, $usuariIDsended);
                        if($emailExistent == false){ 
                            if ($functionsBdD->updateUsuari($usuariData)) {
                                echo json_encode(['status' => 'success']);
                            } else {
                                http_response_code(500);
                                echo json_encode(['status' => 'error', 'message' => 'Failed to update experiment or utilitza.']);
                            }
                        
                        } else {
                            //codi ha de retornar que email ja esta ocupat
                            http_response_code(409); // 409 Conflict
                            echo json_encode(['status' => 'error', 'message' => 'The email is already in use.']);
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Token missing.']);
            }
        }
    }





    else if ($resource == 'dataUser') { 
        if ($method == "GET") {

            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
            if ($authHeader) {

                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
    
                if ($decoded) {

                    $usuariID = $decoded->data->username;
                    $role = $decoded->iss;
                    
                    if ($usuariID){
                        $usuaris = $functionsBdD->getDataUsuari($usuariID);
                        if ($usuaris) {
                                http_response_code(200);
                                echo json_encode($usuaris);
                        } else {
                            http_response_code(404);
                            echo json_encode(array("message" => "No experiments found"));
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Invalid token"));
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Authorization header not found"));
            }
        } else {
            http_response_code(405);
            echo json_encode(array("message" => "Method not allowed"));
        }
    }





    else if ($resource == 'UpdateMyUsuari') {
        if ($method == "POST") {
            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if ($authHeader) {
                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = $jwtHandler->decodeToken($token);
                
                if ($decoded) {
                    $usuariID = $decoded->data->username;
                    $role = $decoded->iss;
                    $usuariData = json_decode(file_get_contents("php://input"), true);
                    $usuariEmail = $usuariData['email'];
                    $usuariIDsended = $usuariData['usuariID'];
                    if ($usuariID === $usuariIDsended){ 
                        $emailExistent = $functionsBdD->comprovarEmail($usuariEmail, $usuariIDsended);
                        if($emailExistent == false){ 
                            if ($functionsBdD->updateMyUsuari($usuariData)) {
                                echo json_encode(['status' => 'success']);
                            } else {
                                http_response_code(500);
                                echo json_encode(['status' => 'error', 'message' => 'Failed to update experiment or utilitza.']);
                            }
                        
                        } else {
                            //codi ha de retornar que email ja esta ocupat
                            http_response_code(409); // 409 Conflict
                            echo json_encode(['status' => 'error', 'message' => 'The email is already in use.']);
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Token missing.']);
            }
        }
    }






    else {
        // només validem /contrasenya/validar

        header('HTTP/1.1 200 OK');
      }
  }
}

$server = new Server;
$server->serve();
