import { registerBlockType, createBlock } from '@wordpress/blocks';
import { useBlockProps, RichText, MediaUpload } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { Button, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';


registerBlockType('pos/img-describe', {
    edit: EditComponent,
    save: SaveComponent,
    transforms: {
        from: [
            {
                type: 'block',
                blocks: ['core/image'],
                transform: (attributes) => {
                    console.log( 'Transforming image', attributes );
                    return createBlock('pos/img-describe', {
                        id: attributes.id,
                        url: attributes.url,
                        alt: attributes.alt,
                        caption: attributes.caption,
                        processed: 0,
                    });
                },
            },
        ],
    },
});

function EditComponent({ attributes, setAttributes }) {
    const { id, url, alt, caption, processed } = attributes;
    const blockProps = useBlockProps();
    useEffect(() => {
        if ( processed === 0 ) {
            setAttributes( { processed: 1 } );
            generateImageDescription( id );
        }
    }, [ processed ]);

    const onSelectImage = (media) => {
        setAttributes( {
            id: media.id,
            url: media.url,
            alt: media.alt,
            caption: media.caption,
            processed: 0,
        } );
        // Call AI service to generate description
    };

    const generateImageDescription = async (id) => {
        // Implement API call to AI service for image recognition
        // This is a placeholder and needs to be replaced with actual API call
        const response = await apiFetch( {
			path: '/pos/v1/openai/media/describe/' + id,
            method: 'POST',
		} );
        console.log( 'Generating image description', response );
        setAttributes({ caption: response.description, processed: Math.floor(Date.now()/1000) });
    };

    return (
        <div {...blockProps}>
            { ! url && (
                <Placeholder>
                    <MediaUpload
                        onSelect={onSelectImage}
                        allowedTypes={['image']}
                        render={({ open }) => (
                            <Button onClick={open}>
                                { __('Upload Image') }
                            </Button>
                        )}
                    />
                </Placeholder>
            ) }
            { url && <img src={url} alt={alt} /> }
            { url &&<RichText
                tagName="figcaption"
                placeholder={__('Write caption…')}
                value={caption}
                onChange={(caption) => setAttributes({ caption })}
            /> }
        </div>
    );
}

function SaveComponent({ attributes }) {
    const { url, alt, caption, description } = attributes;
    const blockProps = useBlockProps.save();

    return (
        <figure {...blockProps}>
            {url && <img src={url} alt={alt} />}
            {caption && <figcaption>{caption}</figcaption>}
        </figure>
    );
}
