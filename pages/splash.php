<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decanter - Stanford’s Open Source Design and CSS Framework</title>
    <link rel="stylesheet" href="https://decanter.stanford.edu/_next/static/css/efe79e0296b2804a.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Mono&family=Roboto+Slab:wght@300;400;700&family=Source+Sans+3:wght@400;600;700&family=Source+Serif+4:wght@400;600;700&display=swap">
    <style>
        body, html {
            font-family: 'Source Sans 3', sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        h1, h2 {
            color: #8c1515;
        }
        .main-content {
            flex: 1;
        }
        .hero {
            display: flex;
            align-items: center;
            background: url('https://picsum.photos/1200/600') no-repeat center center;
            background-size: cover;
            color: #fff;
            padding: 6rem 2rem; /* Increased padding for height */
        }
        .hero-content {
            max-width: 600px;
            text-align: left;
        }
        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .hero-buttons {
            display: flex;
            gap: 1rem;
        }
        .btn-blue, .btn-red {
            padding: 1rem 2rem;
            font-size: 1.2rem; /* Increased font size */
            color: #fff;
            text-align: center;
            width: 100%;
            font-weight: bold;
            border-radius: 5px;
        }
        .btn-blue {
            background-color: #004080;
        }
        .btn-red {
            background-color: #8c1515;
        }
        .cards-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 3rem auto;
            padding: 1rem;
            max-width: 1200px;
            text-align: center;
        }
        .card {
            background: #f5f5f5;
            padding: 2rem;
            border-radius: 8px;
        }
        .card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        footer {
            background-color: #8c1515;
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="px-20 sm:px-30 md:px-50 lg:px-30 pt-5 pb-1 bg-digital-red">
    <a class="logo hocus:no-underline text-white hocus:text-white text-20 leading-none" href="https://www.stanford.edu">Stanford University</a>
</div>

<div class="main-content">
    <!-- Hero Section -->
    <div class="hero">
        <div class="hero-content">
            <h1>Research Study Universal Intake</h1>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
            <div class="hero-buttons">
                <a href="#dashboard" class="btn-blue">My Intakes Dashboard</a>
                <a href="#new-intake" class="btn-red">Start New Intake Request</a>
            </div>
        </div>
    </div>

    <!-- Cards Section -->
    <section class="cards-section">
        <div class="card">
            <h3>Intake 1</h3>
            <p>A short description of the first intake. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
        </div>
        <div class="card">
            <h3>Intake 2</h3>
            <p>A short description of the second intake. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
        </div>
        <div class="card">
            <h3>Intake 3</h3>
            <p>A short description of the third intake. Ut enim ad minim veniam, quis nostrud exercitation ullamco.</p>
        </div>
        <div class="card">
            <h3>Intake 4</h3>
            <p>A short description of the fourth intake. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore.</p>
        </div>
        <div class="card">
            <h3>Intake 5</h3>
            <p>A short description of the fifth intake. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt.</p>
        </div>
    </section>
</div>

<!-- Footer -->
<footer class="cc global-footer basefont-20 rs-py-1 text-white bg-digital-red w-full">
    <div class="flex flex-col lg:flex-row" title="Common Stanford resources">
        <div class="text-center mt-5 mb-9">
            <a class="logo hocus:no-underline text-white hocus:text-white type-3" href="https://www.stanford.edu">Stanford<br>University</a>
        </div>
        <div class="lg:pl-45 xl:pl-50 text-left sm:text-center lg:text-left grow">
            <nav aria-label="global footer menu" class="flex flex-row sm:flex-col justify-center sm:items-center lg:items-start mb-10">
                <ul class="list-unstyled mb-10 sm:mb-4 mr-19 sm:mr-0 p-0 text-15 md:text-17 2xl:text-18 flex flex-col sm:flex-row">
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://www.stanford.edu" class="text-white no-underline hocus:underline hocus:text-white">Stanford Home<span class="sr-only">(link is external)</span></a></li>
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://visit.stanford.edu/plan/" class="text-white no-underline hocus:underline hocus:text-white">Maps & Directions<span class="sr-only">(link is external)</span></a></li>
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://www.stanford.edu/search/" class="text-white no-underline hocus:underline hocus:text-white">Search Stanford<span class="sr-only">(link is external)</span></a></li>
                    <li><a href="https://emergency.stanford.edu" class="text-white no-underline hocus:underline hocus:text-white">Emergency Info<span class="sr-only">(link is external)</span></a></li>
                </ul>
                <ul class="list-unstyled mb-10 sm:mb-0 ml-19 sm:ml-0 p-0 text-15 sm:text-14 md:text-15 xl:text-16 flex flex-col sm:flex-row sm:link-regular">
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://www.stanford.edu/site/terms/" title="Terms of use for sites" class="text-white no-underline hocus:underline hocus:text-white">Terms of Use<span class="sr-only">(link is external)</span></a></li>
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://www.stanford.edu/site/privacy/" title="Privacy and cookie policy" class="text-white no-underline hocus:underline hocus:text-white">Privacy<span class="sr-only">(link is external)</span></a></li>
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://uit.stanford.edu/security/copyright-infringement" title="Report alleged copyright infringement" class="text-white no-underline hocus:underline hocus:text-white">Copyright<span class="sr-only">(link is external)</span></a></li>
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://adminguide.stanford.edu/chapter-1/subchapter-5/policy-1-5-4" title="Ownership and use of Stanford trademarks and images" class="text-white no-underline hocus:underline hocus:text-white">Trademarks<span class="sr-only">(link is external)</span></a></li>
                    <li class="sm:mr-10 md:mr-20 lg:mr-27"><a href="https://studentservices.stanford.edu/more-resources/student-policies/non-academic/non-discrimination" title="Non-discrimination policy" class="text-white no-underline hocus:underline hocus:text-white">Non-Discrimination<span class="sr-only">(link is external)</span></a></li>
                    <li><a href="https://www.stanford.edu/site/accessibility" title="Report web accessibility issues" class="text-white no-underline hocus:underline hocus:text-white">Accessibility<span class="sr-only">(link is external)</span></a></li>
                </ul>
            </nav>
            <div class="text-13 sm:text-14 text-center lg:text-left">
                <span class="whitespace-no-wrap">© Stanford University.</span>
                <span class="whitespace-no-wrap">&nbsp; Stanford, California 94305.</span>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
