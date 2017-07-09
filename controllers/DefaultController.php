<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\gii\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DefaultController extends Controller
{
    public $layout = 'generator';
    /**
     * @var \yii\gii\Module
     */
    public $module;
    /**
     * @var \yii\gii\Generator
     */
    public $generator;


    public function actionIndex()
    {
        $this->layout = 'main';

        return $this->render('index');
    }

    public function actionView($id)
    {
        $generator = $this->loadGenerator($id);
        $params = ['generator' => $generator, 'id' => $id];

        $preview = Yii::$app->request->post('preview');
        $generate = Yii::$app->request->post('generate');
        $answers = Yii::$app->request->post('answers');

        if ($preview !== null || $generate !== null) {
            if ($generator->validate()) {
                $generator->saveStickyAttributes();
                $files = $generator->generate();
                if ($generate !== null && !empty($answers)) {
                    $params['hasError'] = !$generator->save($files, (array) $answers, $results);
                    $params['results'] = $results;
                } else {
                    $params['files'] = $files;
                    $params['answers'] = $answers;
                }
            }
        }

        return $this->render('view', $params);
    }

    public function actionPreview($id, $file)
    {
        $generator = $this->loadGenerator($id);
        if ($generator->validate()) {
            foreach ($generator->generate() as $f) {
                if ($f->id === $file) {
                    $content = $f->preview();
                    if ($content !== false) {
                        return  '<div class="content">' . $content . '</div>';
                    } else {
                        return '<div class="error">Preview is not available for this file type.</div>';
                    }
                }
            }
        }
        throw new NotFoundHttpException("Code file not found: $file");
    }

    public function actionDiff($id, $file)
    {
        $generator = $this->loadGenerator($id);
        if ($generator->validate()) {
            foreach ($generator->generate() as $f) {
                if ($f->id === $file) {
                    return $this->renderPartial('diff', [
                        'diff' => $f->diff(),
                    ]);
                }
            }
        }
        throw new NotFoundHttpException("Code file not found: $file");
    }

    /**
     * Runs an action defined in the generator.
     * Given an action named "xyz", the method "actionXyz()" in the generator will be called.
     * If the method does not exist, a 400 HTTP exception will be thrown.
     * @param string $id the ID of the generator
     * @param string $name the action name
     * @return mixed the result of the action.
     * @throws NotFoundHttpException if the action method does not exist.
     */
    public function actionAction($id, $name)
    {
        $generator = $this->loadGenerator($id);
        $method = 'action' . $name;
        if (method_exists($generator, $method)) {
            return $generator->$method();
        } else {
            throw new NotFoundHttpException("Unknown generator action: $name");
        }
    }

    /**
     * Loads the generator with the specified ID.
     * @param string $id the ID of the generator to be loaded.
     * @return \yii\gii\Generator the loaded generator
     * @throws NotFoundHttpException
     */
    protected function loadGenerator($id)
    {
        if (isset($this->module->generators[$id])) {
            $this->generator = $this->module->generators[$id];
            $this->generator->loadStickyAttributes();
            $this->generator->load(Yii::$app->request->post());

            return $this->generator;
        } else {
            throw new NotFoundHttpException("Code generator not found: $id");
        }
    }

    public function actionGenAll()
    {
        /** @var yii\gii\generators\model\Generator[] $generator */
        $generator = [];

        $db = Yii::$app->db;
        $params['hasError'] = '';
        $params['results'] = '';
        $answers = null;
        $i = 1;
        foreach ($db->schema->getTableNames() as $tableName) {
            $generator[$i] = new yii\gii\generators\model\Generator;
            $generator[$i]->loadStickyAttributes();

            $generator[$i]->load([
                'Generator' => [
                    'tableName' => $tableName,
                    'modelClass' => $generator[$i]->generateClassName($tableName),
                    'ns' => 'app\modelsDB',
                    'baseClass' => 'yii\db\ActiveRecord',
                    'db' => 'db',
                    'useTablePrefix' => 0,
                    'generateRelations' => 'all',
                    'generateLabelsFromComments' => 0,
                    'generateQuery' => 0,
                    'queryNs' => 'app\modelsDB',
                    'queryClass' => $tableName . 'Query',
                    'queryBaseClass' => 'yii\db\ActiveQuery',
                    'enableI18N' => 1,
                    'messageCategory' => 'app',
                    'useSchemaName' => 1,
                    'template' => 'default',
                ],
                'generate' => null,

            ]);

            if ($generator[$i]->validate()) {
                $generator[$i]->saveStickyAttributes();
                $files = $generator[$i]->generate();

                $params['hasError'] .= !$generator[$i]->save($files, [$files[0]->id => 1], $results);
                $params['results'] .= $results;

            }
            $i++;
        }
        $params = ['generator' => $generator[1], 'id' => 'model'];

        return $this->render('view', $params);
    }
}
