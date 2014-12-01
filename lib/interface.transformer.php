<?php

Interface Transformer
{
    /**
     * The content type of this transformer's format
     * @return string
     */
    public function accepts();

    /**
     * Accepts a single string parameter and returns
     * back the data in the format specified by this
     * Transformer.
     *
     * @param string $data
     * @return string
     */
    public function transform($data);
}