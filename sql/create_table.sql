-- 法令情報を保存するテーブル
CREATE TABLE laws (
    id INT AUTO_INCREMENT PRIMARY KEY,
    law_title VARCHAR(255) NOT NULL,    -- 法令名
    law_num VARCHAR(100),               -- 法令番号
    dropbox_url TEXT,                   -- Dropboxへのリンク
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 条文の内容を保存するテーブル
CREATE TABLE law_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    law_id INT,                         -- どの法令に紐づくか
    chapter_title VARCHAR(255),         -- 章
    article_title VARCHAR(255),         -- 条番号
    paragraph_num VARCHAR(50),          -- 項
    content_text TEXT,                  -- 本文
    FOREIGN KEY (law_id) REFERENCES laws(id) ON DELETE CASCADE
);