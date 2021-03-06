<?php
function standard_vars() {
	echo <<<EOD
newJobFlag    smallint;
	databaseName  varchar(100);
	procedureName varchar(100);
	jobID         bigint;
	stepCt        bigint;
	rowCt         bigint;
	errorNumber   varchar;
	errorMessage  varchar;

EOD;
}

function func_start($procName) {
	echo <<<EOD
--Set Audit Parameters
	newJobFlag := 0; -- False (Default)
	jobID := currentJobID;
	SELECT current_user INTO databaseName; --(sic)
	procedureName := '$procName';

	--Audit JOB Initialization
	--If Job ID does not exist, then this is a single procedure run and we need to create it
	IF (coalesce(jobID::text, '') = '' OR jobID < 1)
		THEN
		newJobFlag := 1; -- True
		SELECT cz_start_audit(procedureName, databaseName) INTO jobID;
	END IF;
	PERFORM cz_write_audit(jobId, databaseName, procedureName,
		'Start FUNCTION', 0, stepCt, 'Done');
	stepCt := 1;

EOD;
}

function step_begin() {
	echo "BEGIN\n";
}

$ret_part =	$RETURN_METHOD == 'RETURN'
			? 'RETURN -16;'
			: "rtn_code := -16;\n\t\tRETURN;";

$error_handle_body = <<<EOD
errorNumber := SQLSTATE;
		errorMessage := SQLERRM;
		PERFORM cz_error_handler(jobID, procedureName, errorNumber, errorMessage);
		PERFORM cz_end_audit (jobID, 'FAIL');
		$ret_part
EOD;

function  error_handle_body() {
	echo $GLOBALS['error_handle_body'], "\n";
}

function step_end($message, $count=null) {
	if (strchr($message, "'") === false) {
		$message = "'$message'";
	}
	$countStr = $count !== null ? $count : "rowCt";

	if ($count === null) {
		echo "GET DIAGNOSTICS rowCt := ROW_COUNT;\n\t";
	}

	echo <<<EOD
PERFORM cz_write_audit(jobId, databaseName, procedureName,
		$message, $countStr, stepCt, 'Done');
	stepCt := stepCt + 1;
	EXCEPTION
		WHEN OTHERS THEN
		$GLOBALS[error_handle_body]
	END;

EOD;

}

/* stuff to output on the header of the generated file: */
echo <<<EOD
-----------------------------------------------------------------------
--             DO NOT EDIT THIS FILE. IT IS AUTOGENERATED            --
-- Edit the original file in the macroed_functions directory instead --
-----------------------------------------------------------------------

EOD;
