<?php
class AIController {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function generateRecommendations($evaluationId) {
        try {
            // Get evaluation data with details
            $evaluation = $this->getEvaluationWithDetails($evaluationId);
            
            if (!$evaluation) {
                throw new Exception("Evaluation not found");
            }
            
            $recommendations = [];
            
            // Analyze communications performance
            $commAnalysis = $this->analyzeCommunications($evaluation['details']['communications']);
            if ($commAnalysis['needs_improvement']) {
                $recommendations[] = [
                    'area' => 'Communication Skills',
                    'suggestion' => $commAnalysis['suggestion'],
                    'priority' => $commAnalysis['priority']
                ];
            }
            
            // Analyze management performance
            $mgmtAnalysis = $this->analyzeManagement($evaluation['details']['management']);
            if ($mgmtAnalysis['needs_improvement']) {
                $recommendations[] = [
                    'area' => 'Lesson Management',
                    'suggestion' => $mgmtAnalysis['suggestion'],
                    'priority' => $mgmtAnalysis['priority']
                ];
            }
            
            // Analyze assessment performance
            $assessAnalysis = $this->analyzeAssessment($evaluation['details']['assessment']);
            if ($assessAnalysis['needs_improvement']) {
                $recommendations[] = [
                    'area' => 'Student Assessment',
                    'suggestion' => $assessAnalysis['suggestion'],
                    'priority' => $assessAnalysis['priority']
                ];
            }
            
            // Overall recommendation
            $overallAnalysis = $this->analyzeOverall($evaluation['overall_avg']);
            if ($overallAnalysis['needs_improvement']) {
                $recommendations[] = [
                    'area' => 'Overall Teaching Performance',
                    'suggestion' => $overallAnalysis['suggestion'],
                    'priority' => 'high'
                ];
            }
            
            // Save recommendations
            $this->saveRecommendations($evaluationId, $recommendations);
            
            return $recommendations;
            
        } catch (Exception $e) {
            error_log("AI Recommendation Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getEvaluationWithDetails($evaluationId) {
        // Get evaluation basic info
        $query = "SELECT * FROM evaluations WHERE id = :evaluation_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluationId);
        $stmt->execute();
        $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evaluation) return null;
        
        // Get evaluation details
        $detailsQuery = "SELECT * FROM evaluation_details WHERE evaluation_id = :evaluation_id";
        $detailsStmt = $this->db->prepare($detailsQuery);
        $detailsStmt->bindParam(':evaluation_id', $evaluationId);
        $detailsStmt->execute();
        $details = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize details by category
        $organizedDetails = [
            'communications' => [],
            'management' => [],
            'assessment' => []
        ];
        
        foreach ($details as $detail) {
            $organizedDetails[$detail['category']][] = $detail;
        }
        
        $evaluation['details'] = $organizedDetails;
        return $evaluation;
    }
    
    private function analyzeCommunications($communications) {
        $avgScore = $this->calculateAverageScore($communications);
        
        if ($avgScore < 2.5) {
            return [
                'needs_improvement' => true,
                'suggestion' => 'Consider voice projection exercises and practice speaking more slowly and clearly. Incorporate more interactive questioning techniques to engage students.',
                'priority' => 'high'
            ];
        } elseif ($avgScore < 3.5) {
            return [
                'needs_improvement' => true,
                'suggestion' => 'Continue developing non-verbal communication skills. Try incorporating more varied tone and pacing to maintain student engagement.',
                'priority' => 'medium'
            ];
        }
        
        return ['needs_improvement' => false];
    }
    
    private function analyzeManagement($management) {
        $avgScore = $this->calculateAverageScore($management);
        
        if ($avgScore < 2.5) {
            return [
                'needs_improvement' => true,
                'suggestion' => 'Focus on creating clearer learning objectives and connecting lessons to real-world examples. Consider using more visual aids and interactive activities.',
                'priority' => 'high'
            ];
        } elseif ($avgScore < 3.5) {
            return [
                'needs_improvement' => true,
                'suggestion' => 'Enhance lesson introductions to better capture student interest. Try incorporating more varied teaching strategies to address different learning styles.',
                'priority' => 'medium'
            ];
        }
        
        return ['needs_improvement' => false];
    }
    
    private function analyzeAssessment($assessment) {
        $avgScore = $this->calculateAverageScore($assessment);
        
        if ($avgScore < 2.5) {
            return [
                'needs_improvement' => true,
                'suggestion' => 'Implement more formative assessment techniques to monitor student understanding throughout the lesson. Consider using quick polls or exit tickets.',
                'priority' => 'high'
            ];
        } elseif ($avgScore < 3.5) {
            return [
                'needs_improvement' => true,
                'suggestion' => 'Diversify assessment methods to include more project-based and practical evaluations that align with different learning styles.',
                'priority' => 'medium'
            ];
        }
        
        return ['needs_improvement' => false];
    }
    
    private function analyzeOverall($overallScore) {
        if ($overallScore < 3.0) {
            return [
                'needs_improvement' => true,
                'suggestion' => 'Consider attending professional development workshops on classroom management and instructional strategies. Peer observation of highly-rated faculty may provide valuable insights.'
            ];
        }
        
        return ['needs_improvement' => false];
    }
    
    private function calculateAverageScore($criteria) {
        if (empty($criteria)) return 0;
        
        $total = 0;
        foreach ($criteria as $criterion) {
            $total += $criterion['rating'];
        }
        
        return $total / count($criteria);
    }
    
    private function saveRecommendations($evaluationId, $recommendations) {
        $query = "INSERT INTO ai_recommendations (evaluation_id, area, suggestion, priority) 
                  VALUES (:evaluation_id, :area, :suggestion, :priority)";
        
        $stmt = $this->db->prepare($query);
        
        foreach ($recommendations as $recommendation) {
            $stmt->bindParam(':evaluation_id', $evaluationId);
            $stmt->bindParam(':area', $recommendation['area']);
            $stmt->bindParam(':suggestion', $recommendation['suggestion']);
            $stmt->bindParam(':priority', $recommendation['priority']);
            $stmt->execute();
        }
    }
}
?>