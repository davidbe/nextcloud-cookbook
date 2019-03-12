<?php

namespace OCA\Cookbook\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class RecipeDb {
    private $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     */
    public function findRecipeById(int $id) {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('cookbook_recipes')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        $cursor = $qb->execute();
        $row = $cursor->fetch();
        $cursor->closeCursor();

        return $row;
    }
    
    public function findAllRecipes() {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('cookbook_recipes');

        $cursor = $qb->execute();
        $row = $cursor->fetch();
        $cursor->closeCursor();

        return $row;
    }
    
    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
     */
    public function findRecipes($ingredients, $keywords, $limit=null, $offset=null) {
        $qb = $this->db->getQueryBuilder();

        if($keywords && is_array($keywords) || sizeof($keywords) > 0) {
            $qb->select('*')
                ->from('cookbook_keywords', 'k')
                ->where('k.name = \'' . $keywords[0] . '\'');

            for($i = 1; $i < sizeof($keywords); $i++) {
                $qb->orWhere('k.name = \'' . $keywords[$i] . '\'');
            }

            $qb->join('k', 'cookbook_recipes', 'r', 'r.recipe_id = k.recipe_id'); 
        }
        
        if($ingredients && is_array($ingredients) || sizeof($ingredients) > 0) {
            $qb->select('*')
                ->from('cookbook_ingredients', 'i')
                ->where('i.name = \'' . $ingredients[0] . '\'');

            for($i = 1; $i < sizeof($ingredients); $i++) {
                $qb->orWhere('i.name = \'' . $ingredients[$i] . '\'');
            }

            $qb->join('i', 'cookbook_recipes', 'r', 'r.recipe_id = i.recipe_id'); 
        }

        $qb->setMaxResults($limit);
        $qb->setFirstResult($offset);

        $cursor = $qb->execute();
        $row = $cursor->fetch();
        $cursor->closeCursor();

        return $row;
    }

    public function indexRecipeFile($file) {
        $json = json_decode($file->getContent());
        
        if(!$json || !isset($json['id']) || !isset($json['name'])) { return; }
        
        $id = $recipe['id'];
        $qb = $this->db->getQueryBuilder();

        // Insert recipe 
        $qb->delete('cookbook_recipes')
            ->where(
               $qb->expr()->eq('recipe_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        $qb->insert('cookbook_recipes')
            ->values([
                'recipe_id' => $id,
                'name' => $json['name'],
            ]);

        // Insert keywords 
        $qb->delete('cookbook_keywords')
            ->where(
               $qb->expr()->eq('recipe_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );
        
        if(isset($json['keywords'])) {
            foreach(explode(',', $json['keywords']) as $keyword) {
                $keyword = trim($keyword);   
                
                $qb->insert('cookbook_keywords')
                    ->values([
                        'recipe_id' => $id,
                        'name' => $keyword,
                    ]);
            }
        }
        
        // Insert ingredients 
        $qb->delete('cookbook_ingredients')
            ->where(
               $qb->expr()->eq('recipe_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );
        
        if(isset($json['recipeIngredient'])) {
            foreach($json['recipeIngredient'] as $ingredient) {
                if(!is_string($ingredient)) {
                    $ingredient = isset($ingredient['text']) ? $ingredient['text'] : '';
                }

                $ingredient = $preg_replace('/[^a-zA-Z ]+/', '', $ingredient);
                $ingredient = strtolower($ingredient); 
                $ingredient = trim($ingredient);

                if(!$ingredient) { continue; }

                $qb->insert('cookbook_ingredients')
                    ->values([
                        'recipe_id' => $id,
                        'name' => $keyword,
                    ]);
            }
        }
        
        $qb->execute();
    }
}

?>