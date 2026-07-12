-- Nutrition seed data for existing ingredients
-- calories_per_100g = calories per 100 grams of ingredient
-- grams_per_unit = weight in grams for one "unit" (1 cup, 1 tbsp, 1 whole, 1 clove, etc.)

-- Oils, fats, dairy
UPDATE ingredients SET calories_per_100g = 884, grams_per_unit = 14 WHERE name = 'Olive Oil';     -- 1 tbsp = 14g
UPDATE ingredients SET calories_per_100g = 884, grams_per_unit = 14 WHERE name = 'Sesame Oil';    -- 1 tbsp = 14g
UPDATE ingredients SET calories_per_100g = 717, grams_per_unit = 14 WHERE name = 'Butter';        -- 1 tbsp = 14g
UPDATE ingredients SET calories_per_100g = 52,  grams_per_unit = 240 WHERE name = 'Coconut Milk'; -- 1 cup = 240g
UPDATE ingredients SET calories_per_100g = 340, grams_per_unit = 240 WHERE name = 'Heavy Cream';  -- 1 cup = 240g
UPDATE ingredients SET calories_per_100g = 63,  grams_per_unit = 245 WHERE name = 'Whole Milk';   -- 1 cup = 245g
UPDATE ingredients SET calories_per_100g = 265, grams_per_unit = 100 WHERE name = 'Mozzarella';
UPDATE ingredients SET calories_per_100g = 431, grams_per_unit = 100 WHERE name = 'Parmesan';
UPDATE ingredients SET calories_per_100g = 264, grams_per_unit = 100 WHERE name = 'Feta Cheese';
UPDATE ingredients SET calories_per_100g = 97,  grams_per_unit = 100 WHERE name = 'Eggs';          -- 1 large egg = 50g
UPDATE ingredients SET grams_per_unit = 50 WHERE name = 'Eggs';

-- Meats & seafood
UPDATE ingredients SET calories_per_100g = 165, grams_per_unit = 174 WHERE name = 'Chicken Breast'; -- 1 breast = 174g
UPDATE ingredients SET calories_per_100g = 250, grams_per_unit = 113 WHERE name = 'Ground Beef';    -- 1 patty equiv = 113g
UPDATE ingredients SET calories_per_100g = 518, grams_per_unit = 100 WHERE name = 'Pork Belly';
UPDATE ingredients SET calories_per_100g = 208, grams_per_unit = 170 WHERE name = 'Salmon';         -- 1 fillet = 170g
UPDATE ingredients SET calories_per_100g = 85,  grams_per_unit = 150 WHERE name = 'Shrimp';         -- 1 cup = 150g
UPDATE ingredients SET calories_per_100g = 92,  grams_per_unit = 150 WHERE name = 'Squid';
UPDATE ingredients SET calories_per_100g = 86,  grams_per_unit = 150 WHERE name = 'Mussels';

-- Grains & starches
UPDATE ingredients SET calories_per_100g = 130, grams_per_unit = 195 WHERE name = 'Rice';           -- 1 cup cooked = 195g
UPDATE ingredients SET calories_per_100g = 130, grams_per_unit = 195 WHERE name = 'Jasmine Rice';
UPDATE ingredients SET calories_per_100g = 364, grams_per_unit = 125 WHERE name = 'All-Purpose Flour'; -- 1 cup = 125g
UPDATE ingredients SET calories_per_100g = 131, grams_per_unit = 150 WHERE name = 'Pasta';            -- 1 cup cooked = 150g
UPDATE ingredients SET calories_per_100g = 138, grams_per_unit = 180 WHERE name = 'Ramen Noodles';    -- 1 pkg = 180g
UPDATE ingredients SET calories_per_100g = 109, grams_per_unit = 185 WHERE name = 'Rice Noodles';     -- 1 cup cooked = 185g
UPDATE ingredients SET calories_per_100g = 109, grams_per_unit = 185 WHERE name = 'Rice Vermicelli';
UPDATE ingredients SET calories_per_100g = 77,  grams_per_unit = 100 WHERE name = 'Breadcrumbs';
UPDATE ingredients SET calories_per_100g = 77,  grams_per_unit = 60  WHERE name = 'Pita Bread';       -- 1 pita = 60g
UPDATE ingredients SET calories_per_100g = 305, grams_per_unit = 60  WHERE name = 'Corn Tortilla';    -- 1 tortilla = 60g
UPDATE ingredients SET calories_per_100g = 164, grams_per_unit = 200 WHERE name = 'Potatoes';         -- 1 medium = 200g

-- Vegetables
UPDATE ingredients SET calories_per_100g = 40,  grams_per_unit = 150 WHERE name = 'Onion';           -- 1 medium = 150g
UPDATE ingredients SET calories_per_100g = 31,  grams_per_unit = 4   WHERE name = 'Garlic';          -- 1 clove = 4g
UPDATE ingredients SET calories_per_100g = 26,  grams_per_unit = 120 WHERE name = 'Bell Pepper';     -- 1 medium = 120g
UPDATE ingredients SET calories_per_100g = 41,  grams_per_unit = 128 WHERE name = 'Carrots';         -- 1 medium = 128g
UPDATE ingredients SET calories_per_100g = 22,  grams_per_unit = 100 WHERE name = 'Mushrooms';
UPDATE ingredients SET calories_per_100g = 23,  grams_per_unit = 100 WHERE name = 'Spinach';
UPDATE ingredients SET calories_per_100g = 15,  grams_per_unit = 100 WHERE name = 'Cucumber';
UPDATE ingredients SET calories_per_100g = 20,  grams_per_unit = 100 WHERE name = 'Tomato';
UPDATE ingredients SET calories_per_100g = 31,  grams_per_unit = 100 WHERE name = 'Bean Sprouts';
UPDATE ingredients SET calories_per_100g = 31,  grams_per_unit = 100 WHERE name = 'Green Beans';
UPDATE ingredients SET calories_per_100g = 32,  grams_per_unit = 50  WHERE name = 'Green Onions';    -- 1 bunch green = 50g
UPDATE ingredients SET calories_per_100g = 16,  grams_per_unit = 150 WHERE name = 'Avocado';         -- 1/2 avocado = 75g
UPDATE ingredients SET calories_per_100g = 16,  grams_per_unit = 100 WHERE name = 'Cilantro';
UPDATE ingredients SET calories_per_100g = 36,  grams_per_unit = 100 WHERE name = 'Fresh Parsley';
UPDATE ingredients SET calories_per_100g = 30,  grams_per_unit = 5   WHERE name = 'Chives';          -- 1 tbsp chopped = 3g
UPDATE ingredients SET calories_per_100g = 44,  grams_per_unit = 5   WHERE name = 'Fresh Basil';     -- 1 tbsp = 3g
UPDATE ingredients SET calories_per_100g = 101, grams_per_unit = 150 WHERE name = 'Galangal';        -- 1 inch piece
UPDATE ingredients SET calories_per_100g = 80,  grams_per_unit = 150 WHERE name = 'Ginger';          -- 1 inch piece = 15g
UPDATE ingredients SET calories_per_100g = 5,   grams_per_unit = 2   WHERE name = 'Lemongrass';      -- 1 stalk = 10g
UPDATE ingredients SET calories_per_100g = 18,  grams_per_unit = 100 WHERE name = 'Kaffir Lime Leaves';

-- Legumes & proteins
UPDATE ingredients SET calories_per_100g = 139, grams_per_unit = 100 WHERE name = 'Tofu';
UPDATE ingredients SET calories_per_100g = 599, grams_per_unit = 16  WHERE name = 'Peanuts';         -- 1 tbsp = 16g
UPDATE ingredients SET calories_per_100g = 132, grams_per_unit = 100 WHERE name = 'Chickpeas';
UPDATE ingredients SET calories_per_100g = 132, grams_per_unit = 100 WHERE name = 'Black Beans';
UPDATE ingredients SET calories_per_100g = 570, grams_per_unit = 15  WHERE name = 'Tahini';          -- 1 tbsp = 15g
UPDATE ingredients SET calories_per_100g = 35,  grams_per_unit = 28  WHERE name = 'Miso Paste';      -- 1 tbsp = 18g

-- Fruits
UPDATE ingredients SET calories_per_100g = 29,  grams_per_unit = 58  WHERE name = 'Lemon';           -- 1 lemon juice = 58g
UPDATE ingredients SET calories_per_100g = 30,  grams_per_unit = 67  WHERE name = 'Lime';            -- 1 lime juice = 67g

UPDATE ingredients SET calories_per_100g = 85,  grams_per_unit = 240 WHERE name = 'Red Wine';         -- 1 cup = 240ml

-- Sauces & condiments
UPDATE ingredients SET calories_per_100g = 53,  grams_per_unit = 18  WHERE name = 'Soy Sauce';       -- 1 tbsp = 18g
UPDATE ingredients SET calories_per_100g = 10,  grams_per_unit = 18  WHERE name = 'Fish Sauce';      -- 1 tbsp = 18g
UPDATE ingredients SET calories_per_100g = 387, grams_per_unit = 21  WHERE name = 'Sugar';            -- 1 tbsp = 12g
UPDATE ingredients SET calories_per_100g = 82,  grams_per_unit = 20  WHERE name = 'Tomato Paste';    -- 1 tbsp = 16g
UPDATE ingredients SET calories_per_100g = 5,   grams_per_unit = 240 WHERE name = 'Fish Stock';      -- 1 cup = 240g
UPDATE ingredients SET calories_per_100g = 190, grams_per_unit = 16  WHERE name = 'Peanut Butter';

-- Spices & seasonings (negligible calories, < 10 per serving)
UPDATE ingredients SET calories_per_100g = 0,   grams_per_unit = 1   WHERE name = 'Salt';
UPDATE ingredients SET calories_per_100g = 6,   grams_per_unit = 1   WHERE name = 'Black Pepper';
UPDATE ingredients SET calories_per_100g = 20,  grams_per_unit = 7   WHERE name = 'Paprika';          -- 1 tbsp = 7g
UPDATE ingredients SET calories_per_100g = 20,  grams_per_unit = 7   WHERE name = 'Smoked Paprika';
UPDATE ingredients SET calories_per_100g = 15,  grams_per_unit = 2   WHERE name = 'Cumin';            -- 1 tsp = 2g
UPDATE ingredients SET calories_per_100g = 8,   grams_per_unit = 2   WHERE name = 'Turmeric';         -- 1 tsp = 2g
UPDATE ingredients SET calories_per_100g = 6,   grams_per_unit = 4   WHERE name = 'Curry Powder';     -- 1 tsp = 2g
UPDATE ingredients SET calories_per_100g = 10,  grams_per_unit = 2   WHERE name = 'Chili Pepper';     -- 1 tsp = 2g
UPDATE ingredients SET calories_per_100g = 0,   grams_per_unit = 1   WHERE name = 'Saffron';          -- pinch
UPDATE ingredients SET calories_per_100g = 5,   grams_per_unit = 1   WHERE name = 'Oregano';          -- 1 tsp = 1g
UPDATE ingredients SET calories_per_100g = 5,   grams_per_unit = 1   WHERE name = 'Thyme';
UPDATE ingredients SET calories_per_100g = 290, grams_per_unit = 15  WHERE name = 'Vanilla Extract';  -- 1 tbsp = 13g

-- Other
UPDATE ingredients SET calories_per_100g = 283, grams_per_unit = 100 WHERE name = 'Kalamata Olives';
UPDATE ingredients SET calories_per_100g = 35,  grams_per_unit = 28  WHERE name = 'Nori Seaweed';     -- 1 sheet = 3g
UPDATE ingredients SET calories_per_100g = 150, grams_per_unit = 60  WHERE name = 'Sesame Seeds';     -- 1 tbsp = 9g
-- Palm sugar (similar to brown sugar)
UPDATE ingredients SET calories_per_100g = 375, grams_per_unit = 15  WHERE name = 'Palm Sugar';       -- 1 tbsp = 15g
