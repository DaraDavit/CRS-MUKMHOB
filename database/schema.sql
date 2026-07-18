-- 1. Food Types Table
CREATE TABLE `food_types` (
    `food_type_id` INT AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`food_type_id`)
) ENGINE=InnoDB;

-- 2. Regions Table
CREATE TABLE `regions` (
    `region_id` INT AUTO_INCREMENT,
    `food_type_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`region_id`),
    CONSTRAINT `fk_regions_food_type_id` 
        FOREIGN KEY (`food_type_id`) REFERENCES `food_types` (`food_type_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 3. Countries Table
CREATE TABLE `countries` (
    `country_id` INT AUTO_INCREMENT,
    `region_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`country_id`),
    CONSTRAINT `fk_countries_region_id` 
        FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 4. Ingredients Table
CREATE TABLE `ingredients` (
    `ingredient_id` INT AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `calories_per_100g` INT NULL,
    `grams_per_unit` DECIMAL(8,2) NULL,
    PRIMARY KEY (`ingredient_id`),
    UNIQUE KEY `ingredients_name_key` (`name`)
) ENGINE=InnoDB;

-- 5. Users Table
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT,
    `username` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `avatar_url` VARCHAR(500) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(255) NOT NULL DEFAULT 'User',
    `gender` VARCHAR(20) NULL,
    `pronouns` VARCHAR(50) NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `users_email_key` (`email`)
) ENGINE=InnoDB;

-- 6. Main Recipes Table
CREATE TABLE `recipes` (
    `recipe_id` INT AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `user_id` INT NULL,
    `country_id` INT NOT NULL,
    `description` TEXT NULL,
    `instructions` TEXT NOT NULL,
    `youtube_url` VARCHAR(255) NULL,
    `image_url` VARCHAR(500) NULL,
    `prep_time_minutes` INT NULL,
    `cook_time_minutes` INT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`recipe_id`),
    UNIQUE KEY `recipes_name_key` (`name`),
    CONSTRAINT `fk_recipes_user_id` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_recipes_country_id` 
        FOREIGN KEY (`country_id`) REFERENCES `countries` (`country_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 7. Recipe-Ingredients Many-to-Many Join Table
CREATE TABLE `recipe_ingredients` (
    `recipe_id` INT NOT NULL,
    `ingredient_id` INT NOT NULL,
    `quantity` DECIMAL(10, 2) NOT NULL,
    `unit` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`recipe_id`, `ingredient_id`),
    CONSTRAINT `fk_recipe_ingredients_recipe_id` 
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`recipe_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_recipe_ingredients_ingredient_id` 
        FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 8. Reviews Table
CREATE TABLE `reviews` (
    `review_id` INT AUTO_INCREMENT,
    `recipe_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` INT NOT NULL,
    `comment` TEXT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`review_id`),
    UNIQUE KEY `unique_recipe_user_review` (`recipe_id`, `user_id`),
    CONSTRAINT `fk_reviews_recipe_id` 
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`recipe_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reviews_user_id` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 9. Saved / Favorite Recipes Table
CREATE TABLE `favorite_recipes` (
    `user_id` INT NOT NULL,
    `recipe_id` INT NOT NULL,
    PRIMARY KEY (`user_id`, `recipe_id`),
    CONSTRAINT `fk_favorite_recipes_user_id` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_favorite_recipes_recipe_id` 
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`recipe_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 10. Categories Table
CREATE TABLE `categories` (
    `category_id` INT AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `categories_name_key` (`name`)
) ENGINE=InnoDB;

-- 11. Recipe-Categories Many-to-Many Join Table
CREATE TABLE `recipe_categories` (
    `recipe_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    PRIMARY KEY (`recipe_id`, `category_id`),
    CONSTRAINT `fk_recipe_categories_recipe_id`
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`recipe_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_recipe_categories_category_id`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 12. Contact Messages Table
CREATE TABLE `contact_messages` (
    `message_id` INT AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`message_id`)
) ENGINE=InnoDB;