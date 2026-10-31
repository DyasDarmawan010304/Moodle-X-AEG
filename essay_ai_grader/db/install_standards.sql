-- Moodle database migration for local_essay_ai_grader standards table
-- This creates the local_essay_ai_standard table to store official educational standards

-- Source types: kemendikbud (Ministry of Education), kabupaten (Regency), kota (City), 
-- artikel (Article), jurnal (Journal), custom (Teacher-created)

CREATE TABLE mdl_local_essay_ai_standard (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- Document information
    assignmentid BIGINT NOT NULL COMMENT 'Assignment this standard is for (0 = global)',
    standard_name VARCHAR(255) NOT NULL COMMENT 'Standard name',
    description TEXT COMMENT 'Standard description',
    
    -- Source hierarchy (kemendikbud=1, kabupaten=2, kota=3, artikel=4, jurnal=5, custom=6)
    source VARCHAR(50) NOT NULL COMMENT 'Source: kemendikbud|kabupaten|kota|artikel|jurnal|custom',
    priority TINYINT DEFAULT 6 COMMENT 'Priority order (lower = higher priority)',
    
    -- Standard criteria (JSON format)
    -- Example: {"grammar": "Grammar and spelling", "content": "Content accuracy"}
    criteria LONGTEXT NOT NULL COMMENT 'Evaluation criteria as JSON object',
    
    -- Grading scale (JSON format)
    -- Example: {"0-20": "Poor", "21-40": "Fair", "41-60": "Good"}
    grade_scale LONGTEXT COMMENT 'Grade scale/range as JSON object',
    
    -- Rubric guide for scoring
    rubric LONGTEXT COMMENT 'Detailed rubric guidance',
    
    -- Sourcing information
    author VARCHAR(255) COMMENT 'Author/organization providing standard',
    reference_url VARCHAR(500) COMMENT 'URL or reference to standard document',
    kemendikbud_subject VARCHAR(100) COMMENT 'Subject for Kemendikbud standards',
    kemendikbud_level VARCHAR(100) COMMENT 'Education level (e.g., SMA, JHS)',
    
    -- Control
    enabled TINYINT DEFAULT 1 COMMENT '1 = active, 0 = disabled',
    
    -- Timestamps
    timecreated BIGINT NOT NULL COMMENT 'creation timestamp',
    timemodified BIGINT NOT NULL COMMENT 'last modification timestamp',
    
    -- Indexes for common queries
    UNIQUE KEY idx_assignment_source (assignmentid, source),
    KEY idx_source (source),
    KEY idx_enabled (enabled),
    KEY idx_kemendikbud_level (kemendikbud_level),
    KEY idx_created (timecreated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Educational standards for essay assessment';

-- Insert Kemendikbud K13 sample standards
-- Example 1: Paragraph Essay Standard (SMA)

INSERT INTO mdl_local_essay_ai_standard (
    assignmentid,
    standard_name,
    description,
    source,
    priority,
    criteria,
    grade_scale,
    rubric,
    author,
    reference_url,
    kemendikbud_subject,
    kemendikbud_level,
    enabled,
    timecreated,
    timemodified
) VALUES (
    0,
    'Paragraph Structure and Argumentation (SMA)',
    'Kurikulum 2013 standard for evaluating well-structured paragraphs with clear arguments',
    'kemendikbud',
    1,
    '{"thesis_clarity": "Clear statement of main idea or thesis", "supporting_ideas": "Well-developed supporting ideas with evidence", "organization": "Logical flow and coherence between ideas", "language": "Grammar, vocabulary, and sentence structure", "conclusion": "Strong concluding statement that reinforces thesis"}',
    '{"90-100": "Excellent - Meets all criteria with strong mastery", "80-89": "Good - Meets most criteria with solid performance", "70-79": "Satisfactory - Meets basic criteria with minor issues", "60-69": "Fair - Limited performance with several weaknesses", "0-59": "Poor - Does not meet basic criteria"}',
    'Thesis Statement & Topic Development (20%): Student must present clear thesis statement in opening sentence with logical development of ideas in supporting sentences.

Supporting Evidence (25%): Each supporting idea must include specific examples, facts, or explanations. Evidence must directly support the thesis.

Organization & Coherence (20%): Ideas must be arranged logically with clear transitions between sentences. Paragraph must have identifiable opening, development, and closing.

Language Use (20%): Demonstrate appropriate academic vocabulary, varied sentence structures, and correct grammar throughout the paragraph.

Conclusion (15%): Concluding sentence must reinforce main idea and create sense of closure without introducing new information.',
    'Kementerian Pendidikan dan Kebudayaan (Ministry of Education and Culture)',
    'https://www.kemdikbud.go.id/kurikulum-2013',
    'Indonesian Language Arts (Bahasa Indonesia)',
    'SMA (Senior High School)',
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);

-- Example 2: Descriptive Essay Standard (JHS)

INSERT INTO mdl_local_essay_ai_standard (
    assignmentid,
    standard_name,
    description,
    source,
    priority,
    criteria,
    grade_scale,
    rubric,
    author,
    reference_url,
    kemendikbud_subject,
    kemendikbud_level,
    enabled,
    timecreated,
    timemodified
) VALUES (
    0,
    'Descriptive Essay Text Structure (JHS)',
    'Kurikulum 2013 standard for evaluating descriptive writing with detailed sensory language',
    'kemendikbud',
    1,
    '{"identification": "Clear identification of object or topic being described", "description": "Detailed sensory descriptions (sight, sound, smell, taste, touch)", "organization": "Logical arrangement of descriptive details", "vocabulary": "Appropriate and varied descriptive vocabulary", "mechanics": "Grammar, spelling, and punctuation"}',
    '{"85-100": "Excellent - Rich details with vivid sensory language", "70-84": "Good - Clear descriptions with adequate detail", "55-69": "Satisfactory - Basic descriptions with some sensory elements", "40-54": "Fair - Limited descriptions with weak sensory appeal", "0-39": "Poor - Minimal or unclear descriptions"}',
    'Identification (20%): The object, person, place, or animal being described must be clearly identified in the opening. Identification should establish context and perspective.

Description Quality (30%): Use of specific, sensory details that appeal to the reader\'s senses. Descriptions should be concrete rather than abstract. Include details about appearance, texture, smell, sound, or other sensory qualities.

Organization (20%): Details should be arranged in a logical order (spatial, temporal, or by importance). Transitions between details should be clear and natural.

Vocabulary (20%): Use of vivid, descriptive words and phrases. Avoid repetitive language. Demonstrate mastery of adjectives and descriptive verbs.

Technical Proficiency (10%): Correct grammar, spelling, and punctuation. Sentences should be varied in structure and length.',
    'Kementerian Pendidikan dan Kebudayaan (Ministry of Education and Culture)',
    'https://www.kemdikbud.go.id/kurikulum-2013',
    'Indonesian Language Arts (Bahasa Indonesia)',
    'JHS (Junior High School)',
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);
