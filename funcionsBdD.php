<?php
require_once 'conneccioBdD.php';

class FunctionsBdD {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function login($UserEmail, $passwd) {
        try {
            $stmt = $this->conn->prepare("SELECT usuariID FROM usuaris WHERE email = :email AND contrasenya = :contrasenya");
            $stmt->bindParam("email", $UserEmail);
            $stmt->bindParam("contrasenya", $passwd);
            $stmt->execute();
            $user = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->conn->prepare("SELECT rol FROM usuaris WHERE email = :email AND contrasenya = :contrasenya");
            $stmt->bindParam("email", $UserEmail);
            $stmt->bindParam("contrasenya", $passwd);
            $stmt->execute();
            $role = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($user) == 1) {
                return array($user, $role);
            } else {
                return false;
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }


    public function register($nom, $cognoms, $email, $passwd) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO usuaris (nom, cognoms, email, contrasenya, rol) VALUES (:nom, :cognoms, :email, :passwd, 'tecnic')");
            $stmt->bindParam("nom", $nom);
            $stmt->bindParam("cognoms", $cognoms);
            $stmt->bindParam("email", $email);
            $stmt->bindParam("passwd", $passwd);

            if ($stmt->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }


    public function getCompletedAcceptedExperiments() {
        try {
            $sql = "SELECT experimentID FROM experiment WHERE estat = 'acabat' AND comprovacio = 'acceptat'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $results = $stmt->fetchAll();
    
            return $results ? $results : [];
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(array("message" => "Failed to retrieve experiments", "error" => $e->getMessage()));
            return false;
        }
    }
    

    public function getExperimentDetails($experimentID) {
        try {
            // Un simple join per a seleccionar data from diferent tables
            $sql = "SELECT e.experimentID, e.nom_experiment, e.estat, e.comprovacio, e.catalitzador, e.ph, e.temps, e.temperatura, u.grams, u.concentracio, c.nom, c.tipus 
                    FROM experiment e 
                    LEFT JOIN utilitza u ON e.experimentID = u.experimentID 
                    LEFT JOIN component c ON u.componentID = c.componentID 
                    WHERE e.experimentID = :experimentID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':experimentID', $experimentID, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $result = $stmt->fetchAll();
    
            // Select data from experiment
            $chartSql = "SELECT title, dates FROM charts WHERE experimentID = :experimentID";
            $chartStmt = $this->conn->prepare($chartSql);
            $chartStmt->bindParam(':experimentID', $experimentID, PDO::PARAM_INT);
            $chartStmt->execute();
            $chartStmt->setFetchMode(PDO::FETCH_ASSOC);
            $chartData = $chartStmt->fetch();
    
            // Incluir chart data a experiment details
            $experimentDetails = [
                'experiment' => $result ? $result : null,
                'chartData' => $chartData ? $chartData : null
            ];
    
            return $experimentDetails;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(array("message" => "Failed to retrieve experiment details", "error" => $e->getMessage()));
            return false;
        }
    }



    public function createExperiment($usuariID, $nom_experiment, $estat, $comprovacio, $catalitzador, 
                                    $ph, $temps, $temperatura, $componentID, $grams, $concentracio) {
        try {
            $this->conn->beginTransaction();

            // Inserir into the experiment table
            $sqlExperiment = "INSERT INTO experiment (nom_experiment, estat, comprovacio, catalitzador, 
                                                    ph, temps, temperatura, usuariID) 
                            VALUES (:nom_experiment, :estat, :comprovacio, :catalitzador, :ph, :temps, 
                                    :temperatura, :usuariID)";
            $stmt = $this->conn->prepare($sqlExperiment);
            $stmt->execute([
                ':nom_experiment' => $nom_experiment,
                ':estat' => $estat,
                ':comprovacio' => $comprovacio,
                ':catalitzador' => $catalitzador,
                ':ph' => $ph,
                ':temps' => $temps,
                ':temperatura' => $temperatura,
                ':usuariID' => $usuariID
            ]);

            // Get the last inserted experimentID
            $experimentID = $this->conn->lastInsertId();

            // Inserir into the utilitza table (component usage)
            $sqlUtilitza = "INSERT INTO utilitza (experimentID, componentID, grams, concentracio) 
                            VALUES (:experimentID, :componentID, :grams, :concentracio)";
            $stmtUtilitza = $this->conn->prepare($sqlUtilitza);
            $stmtUtilitza->execute([
                ':experimentID' => $experimentID,
                ':componentID' => $componentID,
                ':grams' => $grams,
                ':concentracio' => $concentracio
            ]);

            $this->conn->commit();

            return $experimentID; // Tornar experiment ID
        } catch (PDOException $e) {
            $this->conn->rollBack();
            http_response_code(500); // Internal server Error
            echo json_encode(array("message" => "Failed to create experiment.", "error" => $e->getMessage()));
            return false;
        }
    }

    public function saveChart($experimentID, $title, $dates) {
        try {
            $sql = "INSERT INTO charts (experimentID, title, dates) VALUES (:experimentID, :title, :dates)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':experimentID' => $experimentID,
                ':title' => $title,
                ':dates' => json_encode($dates)
            ]);
            return true;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(array("message" => "Failed to save chart.", "error" => $e->getMessage()));
            return false;
        }
    }


    public function getExperimentsByUser($usuariID) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM experiment WHERE usuariID = :usuariID");
            $stmt->bindParam(":usuariID", $usuariID);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }


    public function getComponents() {
        try {
            // Fetch component details
            $sql = "SELECT * FROM component";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $result = $stmt->fetchAll();
    
            return $result ? $result : null;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(array("message" => "Failed to retrieve component details", "error" => $e->getMessage()));
            return false;
        }
    }


    public function updateExperiment($data) {
        try {
            $sql = "UPDATE experiment SET
                        nom_experiment = :nom_experiment,
                        estat = :estat,
                        catalitzador = :catalitzador,
                        ph = :ph,
                        temps = :temps,
                        temperatura = :temperatura
                    WHERE experimentID = :experimentID";
    
            $stmt = $this->conn->prepare($sql);
    
            // BindValue
            $stmt->bindValue(':nom_experiment', $data['nom_experiment']);
            $stmt->bindValue(':estat', $data['estat']);
            $stmt->bindValue(':catalitzador', $data['catalitzador']);
            $stmt->bindValue(':ph', $data['ph']);
            $stmt->bindValue(':temps', $data['temps']);
            $stmt->bindValue(':temperatura', $data['temperatura']);
            $stmt->bindValue(':experimentID', $data['experimentID']);
    

            return $stmt->execute();
        } catch (PDOException $e) {

            error_log('Error updating experiment: ' . $e->getMessage());
            return false;
        }
    }
    
    public function updateUtilitza($data) {
        try {
            $sql = "UPDATE utilitza SET
                        componentID = :componentID,
                        grams = :grams,
                        concentracio = :concentracio
                    WHERE experimentID = :experimentID";
    
            $stmt = $this->conn->prepare($sql);
    
            // BindValues
            $stmt->bindValue(':componentID', $data['componentID']);
            $stmt->bindValue(':grams', $data['grams']);
            $stmt->bindValue(':concentracio', $data['concentracio']);
            $stmt->bindValue(':experimentID', $data['experimentID']);
    

            return $stmt->execute();
        } catch (PDOException $e) {

            error_log('Error updating utilitza: ' . $e->getMessage());
            return false;
        }
    }


    public function updateChart($data) {
        try {
            // Mirar si chardata existeix i te les propietats neccessaris
            if (isset($data['chartData']) && !empty($data['chartData']['title']) && !empty($data['chartData']['dates'])) {
    
                // Mirar si char existeix pel aquet experimentID
                $sql = "SELECT COUNT(*) FROM charts WHERE experimentID = :experimentID";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':experimentID', $data['experimentID'], PDO::PARAM_INT);
                $stmt->execute();
                $chartExists = $stmt->fetchColumn();
    
                if ($chartExists) {
                    // Si chart existeix - modificar'ho
                    $sql = "UPDATE charts SET
                                title = :title,
                                dates = :dates
                            WHERE experimentID = :experimentID";
                } else {
                    // Si chart no existeix inserir un de nou
                    $sql = "INSERT INTO charts (experimentID, title, dates) VALUES
                            (:experimentID, :title, :dates)";
                }
    
                $stmt = $this->conn->prepare($sql);
    

                $stmt->bindValue(':title', $data['chartData']['title'], PDO::PARAM_STR);
    
                // convertir les dades de l'array al JSON string
                $datesJson = json_encode($data['chartData']['dates']);
                $stmt->bindValue(':dates', $datesJson, PDO::PARAM_STR);
    
                $stmt->bindValue(':experimentID', $data['experimentID'], PDO::PARAM_INT);
    

                return $stmt->execute();
            } else {
                // Si no hi ha chart data no fer res perque char no es obligatori
                return true;
            }
        } catch (PDOException $e) {

            error_log('Error updating chart: ' . $e->getMessage());
            return false;
        }
    }


    //Selecciona al usuari creador del experiment
    public function getExperimentOwnerID($experimentID) {
        try {
            $sql = "SELECT usuariID FROM experiment WHERE experimentID = :experimentID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':experimentID', $experimentID);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result;
        } catch (PDOException $e) {
            error_log('Error fetching experiment owner ID: ' . $e->getMessage());
            return false;
        }
    }

    // Eliminar Experiment
    public function deleteExperiment($experimentID) {
        try {
            // Delete from 'utilitza' table
            $sql = "DELETE FROM utilitza WHERE experimentID = :experimentID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':experimentID', $experimentID);
            $stmt->execute();
    
            // Delete from 'charts' table
            $sql = "DELETE FROM charts WHERE experimentID = :experimentID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':experimentID', $experimentID);
            $stmt->execute();
    
            // Delete from 'experiment' table
            $sql = "DELETE FROM experiment WHERE experimentID = :experimentID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':experimentID', $experimentID);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log('Error deleting experiment: ' . $e->getMessage());
            return false;
        }
    }


    public function getAllExperiments() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM experiment");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }


    public function updateExperimentStatus($experimentID, $comprovacio) {
        try {
            // Prepare the SQL statement to update the experiment status
            $sql = "UPDATE experiment SET comprovacio = :comprovacio, estat = 'acabat' WHERE experimentID = :experimentID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':experimentID', $experimentID);
            $stmt->bindValue(':comprovacio', $comprovacio);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log('Error updating experiment status: ' . $e->getMessage());
            return false;
        }
    }


    public function getPendentExperiments() {
        try {
            $sql = "SELECT experimentID FROM experiment WHERE estat = 'pendent' AND comprovacio = 'no acceptat'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $results = $stmt->fetchAll();
    
            return $results ? $results : [];
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }


    public function getAllUsuaris($usuariID) {
        try {
            // $sql = "SELECT * FROM usuaris WHERE usuariID != :usuariID AND rol != 'administrador'";
            $sql = "SELECT usuariID, nom, cognoms, email, rol FROM usuaris WHERE usuariID != :usuariID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':usuariID', $usuariID);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $results = $stmt->fetchAll();
    
            return $results ? $results : [];
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }


    public function comprovarEmail($usuariEmail, $usuariID) {
        try {
            $sql = "SELECT COUNT(*) FROM usuaris WHERE email = :usuariEmail AND usuariID != :usuariID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':usuariEmail', $usuariEmail, PDO::PARAM_STR);
            $stmt->bindValue(':usuariID', $usuariID, PDO::PARAM_INT);
            $stmt->execute();
    
            // Fetch the count result
            $count = $stmt->fetchColumn();
    
            // Return true if the email exists or false if is not
            return $count > 0;
        } catch (PDOException $e) {
            // Optionally log the exception
            error_log("Error checking email: " . $e->getMessage());
            return false;
        }
    }


    public function updateUsuari($data) {
        try {
            $sql = "UPDATE usuaris SET
                        nom = :nom,
                        cognoms = :cognoms,
                        email = :email,
                        rol = :rol
                    WHERE usuariID = :usuariID";
    
            $stmt = $this->conn->prepare($sql);
    
            // BindValue
            $stmt->bindValue(':nom', $data['nom']);
            $stmt->bindValue(':cognoms', $data['cognoms']);
            $stmt->bindValue(':email', $data['email']);
            $stmt->bindValue(':rol', $data['rol']);
            $stmt->bindValue(':usuariID', $data['usuariID'], PDO::PARAM_INT);
    

            return $stmt->execute();
        } catch (PDOException $e) {

            error_log('Error updating experiment: ' . $e->getMessage());
            return false;
        }
    }


    public function getDataUsuari($usuariID) {
        try {
            // $sql = "SELECT * FROM usuaris WHERE usuariID != :usuariID AND rol != 'administrador'";
            $sql = "SELECT usuariID, nom, cognoms, email, rol FROM usuaris WHERE usuariID = :usuariID";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':usuariID', $usuariID);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $results = $stmt->fetchAll();
    
            return $results ? $results : [];
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }


    public function updateMyUsuari($data) {
        try {
            $sql = "UPDATE usuaris SET
                        nom = :nom,
                        cognoms = :cognoms,
                        email = :email
                    WHERE usuariID = :usuariID";
    
            $stmt = $this->conn->prepare($sql);
    
            // BindValue
            $stmt->bindValue(':nom', $data['nom']);
            $stmt->bindValue(':cognoms', $data['cognoms']);
            $stmt->bindValue(':email', $data['email']);
            $stmt->bindValue(':usuariID', $data['usuariID'], PDO::PARAM_INT);
    

            return $stmt->execute();
        } catch (PDOException $e) {

            error_log('Error updating experiment: ' . $e->getMessage());
            return false;
        }
    }

}
?>