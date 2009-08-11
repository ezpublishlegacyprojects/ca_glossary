<?php
/*
 * Created on 10-08-09
 *
 * ca_glossary operator replaces texts that match a glossary by a template of the word definition.
 *
 */

class CAGlossary
{
    /**
     * Constructor
     *
     */
    function __construct()
    {
    }

    /**
     * operatorList
     *
     * @return array list of template operators hosted by this class
     */
    function operatorList()
    {
        return array( 'ca_glossary' );
    }

    /**
     * namedParameterPerOperator
     *
     * @return true Indicates that {@link eZGlossary::namedParameterList()} should be used for parameters.
     */
    function namedParameterPerOperator()
    {
        return true;
    }

    /**
     * namedParameterList
     *
     * @return array List of operators and their parameters
     */
    function namedParameterList()
    {
        return array( 'ca_glossary' => array(
                                                'xml_text' => array( 'type' => 'text',
                                                                              'required' => true,
                                                                              'default' => array() )
        )
                                  );
    }

    /**
     * modify
     * Called by the template system when the registrated operators are called in templates.
     *
     * @param object $tpl tempalte system object
     * @param string $operatorName name of currently called operator
     * @param array $operatorParameters ignored, as pr {@link eZGlossary::namedParameterPerOperator()}
     * @param string $rootNamespace
     * @param string $currentNamespace
     * @param string $operatorValue byref return value for template operator
     * @param string $namedParameters parameters for the names operators
     */
    function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters )
    {
        switch ( $operatorName )
        {
            case 'ca_glossary':
                {
                    try {
                    $ini = eZIni::instance('glossary.ini');
                    $replacementLimit = $ini->variable('GeneralSettings','ReplacementLimit');

                    $glossaryArray = $this->buildGlossaryArray();
                    $ret = preg_replace($glossaryArray['pattern'], $glossaryArray['replace'], $namedParameters['xml_text'], $replacementLimit);
                    } catch (Exception $e) {
                        $ret = $namedParameters['xml_text'];
                    }
                } break;
        }
        $operatorValue = $ret;
    }

    /*
     * Build related arrays of search pattern and corresponding replacement
     */
    function buildGlossaryArray()
    {
        // initialisation
        $ini = eZIni::instance('glossary.ini');
        $glossaryNodeId = $ini->variable('GeneralSettings','GlossaryNodeID');
        $definitionClassIdentifier = $ini->variable('GeneralSettings','DefinitionClassIdentifier');
        $titleAttributeIdentifier = $ini->variable('GeneralSettings','TitleAttributeIdentifier');
        $definitionAttributeIdentifier = $ini->variable('GeneralSettings','DefinitionAttributeIdentifier');
        $exceptionTags = $ini->variable('GeneralSettings','ExceptionTags');

        $exceptionTagsString = implode('|',$exceptionTags);

        // Fetch glossary definitions
        $params = array( 'Depth'                    => 0,
                         'ClassFilterType'          => 'include',
                         'ClassFilterArray'         => array($definitionClassIdentifier)
                     );
        $definitions = eZContentObjectTreeNode::subTreeByNodeID($params,$glossaryNodeId);

        if ( count($definitions) == 0 )
        {
            throw new Exception();
        }

        // Fetch glossary informations
        $glossaryNode = eZContentObjectTreeNode::fetch($glossaryNodeId);

        if ( !is_object($glossaryNode) )
        {
            throw new Exception();
        }

        $glossaryUrl = $glossaryNode->attribute('url_alias');

        // foreach definition : save search pattern and fetch template for replacement
        $patternArray = array();
        $replaceArray = array();
        foreach ( $definitions as $definition )
        {
            $object = $definition->object();
            if ( !is_object($object) )
            {
                throw new Exception();
            }
            $dataMap = $object->dataMap();
            $title = $dataMap[$titleAttributeIdentifier]->content();
            $definition = $dataMap[$definitionAttributeIdentifier]->content();

            // match the current definition between a word beginning and a word end (\b)
            // and not followed by closing exception tags : (?![^<\/(".$exceptionTagsString.")>]*<\/(".$exceptionTagsString.")>+)
            // and not in between < and > that is to say in a tag, for example title of a img : (?![^(>|<)]*>+)
            $patternArray[] = "/\b".preg_quote( $title, '/' )."\b(?![^<\/(".$exceptionTagsString.")>]*<\/(".$exceptionTagsString.")>+)(?![^(>|<)]*>+)/i";

            $tpl = templateInit();
            $tpl->setVariable( 'title', $title );
            $tpl->setVariable( 'glossaryUrl', $glossaryUrl );
            $tpl->setVariable( 'definition', $definition );
            $replaceArray[] = $tpl->fetch( 'design:ca_glossary.tpl' );
        }

        return array('pattern' => $patternArray,
                     'replace' => $replaceArray
        );
    }


}

?>