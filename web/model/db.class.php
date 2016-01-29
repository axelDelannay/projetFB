<?php
class DB {
    private static $instance = null;
    private $registry;
    private $connexion = null;

    public static function getInstance($arg) {
        if (!self::$instance instanceof self) {
            self::$instance = new self($arg);
        }
        return self::$instance;
    }

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    public function __wakeup() {
        trigger_error('Deserializing is not allowed.', E_USER_ERROR);
    }

    public function getLastError(){
        $a = $this->connexion->errorInfo();
        return $a[2];
    }

    private function __construct($registry) {
        $this->registry = $registry;
        try
        {
            $this->connexion = new PDO(DB_TYPE.":host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME, DB_USER, DB_PASSWORD);
        }catch(PDOException $e)
        {
            print "Error new PDO: ".$e->getMessage()."<br/>";
            die();
        }
    }

    public function addQuizz($quizzName, $startDate, $endDate, $nbQuestionsTotal, $nbQuestionsDisplayed){
        $res = $this->checkQuizzNameExists($quizzName);
        if($res === false){
            $query2 = $this->connexion->prepare('
                INSERT INTO quizz (name, date_start, date_end, questions_nb_total, questions_nb_displayed)
                VALUES(?, ?, ?, ?, ?);
            ');
            if($query2->execute(array($quizzName, $startDate, $endDate, $nbQuestionsTotal, $nbQuestionsDisplayed))){
                return true;
            }else return 'Insertion has been not successful ['.$this->getLastError().']';
        }else return $res;
    }

    public function updateQuizz($id, $name, $date_start, $date_end, $enabled, $questions_nb_displayed, $questions_nb_total){
        $sql = 'UPDATE quizz SET ';
        if($name !== null){
            $sql .= 'name = :name';
        }
        if($date_start !== null){
            $sql .= ', date_start = :date_start';
        }
        if($date_end !== null){
            $sql .= ', date_end = :date_end';
        }
        if($enabled !== null){
            $sql .= ', enabled = :enabled';
        }
        if($questions_nb_displayed !== null){
            $sql .= ', questions_nb_displayed = :questions_nb_displayed';
        }
        if($questions_nb_total !== null){
            $sql .= ', questions_nb_total = :questions_nb_total';
        }
        $sql .= ' WHERE id = :id';
        $query = $this->connexion->prepare($sql);
        $query->bindValue(':id', $id);
        if($name !== null){
            $query->bindValue(':name', $name);
        }
        if($date_start !== null){
            $query->bindValue(':date_start', $date_start);
        }
        if($date_end !== null){
            $query->bindValue(':date_end', $date_end);
        }
        if($enabled !== null){
            $query->bindValue(':enabled', $enabled);
        }
        if($questions_nb_displayed !== null){
            $query->bindValue(':questions_nb_displayed', $questions_nb_displayed);
        }
        if($questions_nb_total !== null){
            $query->bindValue(':questions_nb_total', $questions_nb_total);
        }

        if($query->execute()){
            return true;
        }else return $this->getLastError();
        return true;
    }

    public function checkQuizzNameExists($n){
        if($query = $this->connexion->prepare('SELECT COUNT(*) AS nb FROM quizz WHERE name=?')){
            if($query->execute(array($n))){
                if($query = $query->fetch()){
                    if($query['nb'] == 0){
                        return false;
                    }else return 'The quizz name already exists';
                }else return 'Fetch failed';
            }else return 'Execute for existing quizz has failed';
        }else return 'Looking for existing quizz has failed';
    }

    public function getIdQuizzByName($quizzName){
        $query = $this->connexion->prepare('SELECT id FROM quizz WHERE name=?');
        if($query->execute(array($quizzName))){
            if($query->rowCount() == 1){
                $query = $query->fetch();
                return $query['id'];
            }else return false;
        }else return false;
    }

    public function addQuestion($idQuizz, $question, $rightAnswer, $wrongAnswer){
        //insertion de la question
        $query = $this->connexion->prepare('INSERT INTO question(label, id_quizz) VALUES(?, ?)');
        if($query->execute(array($question, $idQuizz))){
            //insertion des mauvaises propositions
            $idQuestion = $this->connexion->lastInsertId('question_id_seq'); //PURE DELIRE !!!!!!!!!!!!! Explication: table_colonne_seq et il faut vraiment écrire "seq"


            $idWrongAnswer = [];
            foreach($wrongAnswer as $answer){
                $query = $this->connexion->prepare('INSERT INTO proposition(label) VALUES(?)');
                if($query->execute(array($answer))){
                    $idWrongAnswer[] = $this->connexion->lastInsertId('proposition_id_seq');
                }else return 'error2: '.$this->getLastError();
            }
            //insertion de la bonne réponse
            $query = $this->connexion->prepare('INSERT INTO proposition(label) VALUES(?)');
            if($query->execute(array($rightAnswer))){
                $idRightAnswer = $this->connexion->lastInsertId('proposition_id_seq');
            }else return 'error6: '.$this->getLastError();
            //relations entres les questions et propositions
            //mauvaises réponses
            foreach($idWrongAnswer as $idAnswer){
                $query = $this->connexion->prepare('INSERT INTO answer(id_question, id_proposition, is_correct) VALUES(?, ?, 0)') or die('eerrorr');
                if($query->execute(array($idQuestion, $idAnswer))){

                }else return 'error3: '.$this->getLastError();
            }
            //bonne réponse
            $query = $this->connexion->prepare('INSERT INTO answer(id_question, id_proposition, is_correct) VALUES(?, ?, 1)') or die('eerrorr');
            if($query->execute(array($idQuestion, $idRightAnswer))){
                return true;
            }else return 'error4: '.$this->getLastError();



        }else return 'error1: '.$this->getLastError();

    }

    public function toggleQuizzEnabled($idQuizz){
        $query = $this->connexion->prepare('SELECT enabled FROM quizz WHERE id=?');
        $toEnabled = $this->connexion->prepare('UPDATE quizz SET enabled=1 WHERE id=?');
        $toDisabled = $this->connexion->prepare('UPDATE quizz SET enabled=0 WHERE id=?');
        if($query->execute(array($idQuizz))){
            $query = $query->fetch(PDO::FETCH_ASSOC);
            if($query['enabled']){
                return $toDisabled->execute(array($idQuizz));
            }else return $toEnabled->execute(array($idQuizz));
        }else return false;
    }
    
    public function insertUserInfo($data){
        $id_fb = $data['id_fb'];
        $isset = $this->doesUserExist($id_fb);
        if($isset == 0){
            // Insert
            $req = $this->connexion->prepare('INSERT INTO player(is_admin, first_name, last_name, birthday, gender, location, devices, email, books, music, favorite_athletes, application, id_fb) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        }
        elseif($isset == 1){
            // Update
            $req = $this->connexion->prepare('UPDATE player SET is_admin=?, first_name=?, last_name=?, birthday=?, gender=?, location=?, devices=?, email=?, books=?, music=?, favorite_athletes=?, application=? WHERE id_fb=?');
        }
        else{
            return 'error while checking existing user';
        }
        // Execute
        if($req->execute(array_values($data)))
            return true;
        return 'error while fetching user info';
    }

    public function getInfoQuizz($idQuizz = 'ALL'){
        $sql = 'SELECT * FROM quizz '.(($idQuizz == 'ALL')? ' ' : 'WHERE id = ?');
        $sql.= ' ORDER BY enabled DESC, date_end DESC';
        $query = $this->connexion->prepare($sql);
        if($idQuizz == 'ALL'){
            if($query->execute()){
                $quizzs = [];
                while($quizz = $query->fetch(PDO::FETCH_ASSOC)){
                    $quizzs[$quizz['id']] = $quizz;
                }
            }else return $this->getLastError();
        }else{
            if($query->execute(array($idQuizz))){
                $quizzs = [];
                while($quizz = $query->fetch(PDO::FETCH_ASSOC)){
                    $quizzs[$quizz['id']] = $quizz;
                }
            }else return $this->getLastError();
        }
        return $quizzs;
    }

    public function doesUserExist($id_fb){
        $query = $this->connexion->prepare("SELECT COUNT(*) AS nb FROM player WHERE id_fb = ?");
        if($query->execute(array($id_fb)))
        {
            $query = $query->fetch(PDO :: FETCH_ASSOC);
            return $query['nb'];
        }else return false;
    }

    public function getQuestionsByIdQuizz($idQuizz){
        $sql = '
            SELECT 
                q.label AS "question", q.id AS "id_question", q.id_quizz,
                a.is_correct, a.id_proposition,
                p.label AS "proposition",
                qz.name AS "quizz_name", qz.date_start, qz.date_end, qz.enabled, qz.questions_nb_displayed, qz.questions_nb_total
            FROM 
                question q
            JOIN answer a ON a.id_question = q.id
            JOIN proposition p ON p.id = a.id_proposition
            JOIN quizz qz ON qz.id = q.id_quizz
            WHERE id_quizz = ?
            ORDER BY id_question
            ;
        ';

        $query = $this->connexion->prepare($sql);
        if($query->execute(array($idQuizz))){
            $tab = ['id_quizz' => false];
            while($r = $query->fetch(PDO::FETCH_ASSOC)){
                if($tab['id_quizz'] === false){
                    $tab['id_quizz'] = $r['id_quizz'];
                    $tab['quizz_name'] = $r['quizz_name'];
                    $tab['date_start'] = DateTime::createFromFormat('Y-m-d H:i:s', $r['date_start'])->format('d/m/Y H:i');
                    $tab['date_end'] = DateTime::createFromFormat('Y-m-d H:i:s', $r['date_end'])->format('d/m/Y H:i');
                    $tab['enabled'] = $r['enabled'];
                    $tab['questions_nb_displayed'] = $r['questions_nb_displayed'];
                    $tab['questions_nb_total'] = $r['questions_nb_total'];
                }
                $tab['questions'][$r['id_question']]['question'] = $r['question'];
                $tab['questions'][$r['id_question']]['propositions'][$r['id_proposition']]['proposition'] = $r['proposition'];
                $tab['questions'][$r['id_question']]['propositions'][$r['id_proposition']]['is_correct'] = $r['is_correct'];
            }
            return $tab;
        }else return 'Erreur lors de la requête SQL ';
    }

    public function updateLabel($id, $label, $type){
        if($type == 'question'){
            $sql = 'UPDATE question SET label = ? WHERE id = ?';
        }elseif($type == 'proposition'){
            $sql = 'UPDATE proposition SET label = ? WHERE id = ?';
        }else return 'Type not defined';
        
        $query = $this->connexion->prepare($sql);
        if($query->execute(array($label, $id))){
            return true;
        }else return 'Error lors de l execute.. label:'.$label.', id:'.$id.'.';
    }
    
    public function getListPlayer(){
        $req = $this->connexion->prepare("SELECT * FROM player ORDER BY id");
        if($req->execute())
        {
            $likes = ['books','music','favorite_athletes','application','devices'];
            $players = [];
            while($res = $req->fetch(PDO::FETCH_ASSOC)){
                foreach($res as $k => $v)
                {
                    if(in_array($k,$likes))
                    {
                        $v = str_replace('|',', ',$v);
                        $res[$k] = $v;
                    }
                    elseif($k == 'birthday'){
                        $v = $this->getBdayFromDate($v);
                        $res[$k] = $v;
                    }
                    elseif($k == 'gender'){
                        $v = strtoupper(substr($v,0,1));
                        $res[$k] = $v;
                    }
                }
                $players[$res['id']] = $res;
            }
        }else return "error while getting users list";
        
        return $players;
    }
    
    public function getBdayFromDate($birthDate){
        $birthDate = explode("-", $birthDate);
        $age = (date("md", date("U", mktime(0, 0, 0, $birthDate[1], $birthDate[2], $birthDate[0]))) > date("md")
            ? ((date("Y") - $birthDate[0]) - 1)
            : (date("Y") - $birthDate[0]));

        return $age;
    }
}


















