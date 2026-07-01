SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblTrainingPaths', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblTrainingPathScenarios', N'U') IS NOT NULL
BEGIN
    MERGE dbo.tblTrainingPaths AS target
    USING (
        SELECT
            N'workflow_ops_path' AS PathCode,
            N'Workflow Operations Learning Path' AS PathTitle,
            N'Project managers, workflow administrators, implementation teams, support teams' AS Audience,
            N'Recommended Workflow Operations training sequence covering module overview, projects, requirements, traceability, issues, and task follow-up.' AS Description,
            CAST(1 AS BIT) AS ActiveFlag,
            210 AS SortOrder
    ) AS source
    ON target.PathCode = source.PathCode
    WHEN MATCHED THEN
        UPDATE SET
            PathTitle = source.PathTitle,
            Audience = source.Audience,
            Description = source.Description,
            ActiveFlag = source.ActiveFlag,
            SortOrder = source.SortOrder,
            UpdatedDate = SYSUTCDATETIME()
    WHEN NOT MATCHED THEN
        INSERT
            (PathCode, PathTitle, Audience, Description, ActiveFlag, SortOrder)
        VALUES
            (source.PathCode, source.PathTitle, source.Audience, source.Description, source.ActiveFlag, source.SortOrder);

    DELETE FROM dbo.tblTrainingPathScenarios
    WHERE PathCode = N'workflow_ops_path';

    INSERT INTO dbo.tblTrainingPathScenarios
        (PathCode, ScenarioCode, RequiredFlag, SortOrder)
    VALUES
        (N'workflow_ops_path', N'workflow_ops_overview', CAST(1 AS BIT), 10),
        (N'workflow_ops_path', N'workflow_ops_project_register', CAST(1 AS BIT), 20),
        (N'workflow_ops_path', N'workflow_ops_project_form', CAST(1 AS BIT), 30),
        (N'workflow_ops_path', N'workflow_ops_requirements_register', CAST(1 AS BIT), 40),
        (N'workflow_ops_path', N'workflow_ops_requirement_matrix', CAST(1 AS BIT), 50),
        (N'workflow_ops_path', N'workflow_ops_issue_log', CAST(1 AS BIT), 60),
        (N'workflow_ops_path', N'workflow_ops_task_queue', CAST(1 AS BIT), 70);
END;
GO
